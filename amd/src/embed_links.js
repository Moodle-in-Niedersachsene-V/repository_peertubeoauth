// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle. If not, see <http://www.gnu.org/licenses/>.

/**
 * AMD module: rewrite PeerTube links into responsive iframes.
 *
 * Scans the page for anchor tags whose href points to the configured
 * PeerTube instance (either /videos/embed/<uuid> or /w/<uuid> format)
 * and replaces the link – and its containing paragraph if the link is
 * the sole content – with a responsive iframe.
 *
 * Called via js_call_amd from hook_callbacks::before_http_headers().
 * Config is passed as a single options object:
 *   { instanceUrl: string, embedBase: string }
 *
 * @module     repository_peertubeoauth/embed_links
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {

    /**
     * Build a responsive iframe wrapper for a PeerTube embed URL.
     *
     * The wrapper uses the 16:9 padding-bottom trick so the iframe scales
     * correctly on all screen sizes without requiring a fixed height.
     *
     * @param  {string} embedUrl  Full PeerTube embed URL.
     * @return {jQuery}           The outer wrapper element.
     */
    function buildIframe(embedUrl) {
        // Responsives 16:9-iframe mit Breitenbegrenzung.
        // Moderne Browser: aspect-ratio + max-width, kein padding-bottom-Hack nötig.
        // Ältere Browser (kein aspect-ratio): Fallback über padding-bottom auf
        // einem inneren Wrapper, damit max-width korrekt greift.
        var supportsAspectRatio = CSS && CSS.supports && CSS.supports('aspect-ratio', '16/9');

        var $wrapper = $('<div>', {
            'class': 'peertube-embed-wrapper',
            'style': [
                'display: block',
                'width: 100%',
                'max-width: 560px',
                'margin: 0.75em 0',
            ].join('; ')
        });

        var $inner;
        if (supportsAspectRatio) {
            // Moderner Pfad: aspect-ratio übernimmt die Höhenberechnung.
            $inner = $('<div>', {
                'style': [
                    'position: relative',
                    'width: 100%',
                    'aspect-ratio: 16 / 9',
                    'overflow: hidden',
                ].join('; ')
            });
        } else {
            // Fallback: padding-bottom bezieht sich jetzt auf die Breite des
            // inneren Divs (= max-width des Wrappers), nicht auf den Viewport.
            $inner = $('<div>', {
                'style': [
                    'position: relative',
                    'width: 100%',
                    'padding-bottom: 56.25%',
                    'height: 0',
                    'overflow: hidden',
                ].join('; ')
            });
        }

        var $iframe = $('<iframe>', {
            src:             embedUrl,
            frameborder:     '0',
            allowfullscreen: true,
            sandbox:         'allow-same-origin allow-scripts allow-popups',
            title:           'PeerTube video',
            style: [
                'position: absolute',
                'top: 0',
                'left: 0',
                'width: 100%',
                'height: 100%',
                'border: 0',
            ].join('; ')
        });

        $inner.append($iframe);
        $wrapper.append($inner);
        return $wrapper;
    }

    /**
     * Append embed parameters to a URL, merging with any existing query
     * string. Parameters already present in the URL are NOT overwritten,
     * so an author who deliberately set e.g. ?start=30s keeps it, while
     * the privacy defaults (p2p=0 etc.) are still added.
     *
     * @param  {string} url          The embed URL (may already have a query).
     * @param  {string} embedParams  Query string without leading '?', e.g.
     *                                'peertubeLink=0&p2p=0&warningTitle=0'.
     * @return {string}              URL with merged parameters.
     */
    function appendParams(url, embedParams) {
        if (!embedParams) {
            return url;
        }

        // Split off any existing hash to re-append at the end.
        var hash = '';
        var hashIdx = url.indexOf('#');
        if (hashIdx !== -1) {
            hash = url.slice(hashIdx);
            url = url.slice(0, hashIdx);
        }

        // Collect keys already present in the URL's query string.
        var existing = {};
        var qIdx = url.indexOf('?');
        if (qIdx !== -1) {
            var qs = url.slice(qIdx + 1);
            qs.split('&').forEach(function(pair) {
                if (!pair) {
                    return;
                }
                var key = pair.split('=')[0];
                if (key) {
                    existing[key] = true;
                }
            });
        }

        // Only add params whose key is not already set on the URL.
        var toAdd = [];
        embedParams.split('&').forEach(function(pair) {
            if (!pair) {
                return;
            }
            var key = pair.split('=')[0];
            if (key && !existing[key]) {
                toAdd.push(pair);
            }
        });

        if (toAdd.length === 0) {
            return url + hash;
        }

        var sep = (qIdx === -1) ? '?' : '&';
        return url + sep + toAdd.join('&') + hash;
    }

    /**
     * Convert a watch URL (/w/<uuid>) to an embed URL (/videos/embed/<uuid>).
     * Embed URLs are already returned as-is. Embed parameters are merged in.
     *
     * @param  {string} href         The anchor href.
     * @param  {string} instanceUrl  PeerTube base URL (no trailing slash).
     * @param  {string} embedBase    Embed base URL (instanceUrl + /videos/embed/).
     * @param  {string} embedParams  Query string to merge in (no leading '?').
     * @return {string|null}         Embed URL, or null if not a PeerTube link.
     */
    function toEmbedUrl(href, instanceUrl, embedBase, embedParams) {
        // Normalise: strip trailing slash and query/hash for matching.
        var clean = href.split('?')[0].split('#')[0].replace(/\/$/, '');

        // Already an embed URL?
        var embedPrefix = instanceUrl + '/videos/embed/';
        if (clean.indexOf(embedPrefix) === 0) {
            // Keep original href (may include query params like ?loop=1)
            // and merge in the configured embed params without clobbering.
            return appendParams(href, embedParams);
        }

        // Watch URL: /w/<uuid> or /videos/watch/<uuid>
        var watchPrefixShort = instanceUrl + '/w/';
        var watchPrefixLong = instanceUrl + '/videos/watch/';

        var uuid = null;
        if (clean.indexOf(watchPrefixShort) === 0) {
            uuid = clean.slice(watchPrefixShort.length);
        } else if (clean.indexOf(watchPrefixLong) === 0) {
            uuid = clean.slice(watchPrefixLong.length);
        }

        if (!uuid) {
            return null;
        }

        // Basic UUID / short-UUID sanity check (alphanumeric + hyphens).
        if (!/^[0-9a-zA-Z-]+$/.test(uuid)) {
            return null;
        }

        return appendParams(embedBase + uuid, embedParams);
    }

    /**
     * Scan the page and replace matching PeerTube links with iframes.
     *
     * Only links that are either:
     *  (a) the sole content of their parent block element, or
     *  (b) explicitly marked with data-peertube-embed="1"
     * are converted, to avoid destroying inline text links.
     *
     * @param {string} instanceUrl
     * @param {string} embedBase
     * @param {string} embedParams
     */
    function processLinks(instanceUrl, embedBase, embedParams) {
        $('a[href]').each(function() {
            var $a = $(this);
            var href = $a.attr('href') || '';

            // Quick pre-filter: must contain the instance hostname.
            if (href.indexOf(instanceUrl) === -1) {
                return;
            }

            var embedUrl = toEmbedUrl(href, instanceUrl, embedBase, embedParams);
            if (!embedUrl) {
                return;
            }

            // Decide whether to embed:
            // 1. Explicit opt-in via data attribute.
            // 2. Link is the only meaningful content in its parent.
            var forceEmbed = $a.data('peertube-embed') === 1 ||
                             $a.attr('data-peertube-embed') === '1';

            var $parent = $a.parent();

            if (!forceEmbed) {
                var parentText = ($parent.text() || '').trim();
                var linkText = ($a.text() || '').trim();
                // Only embed when the link fills the whole parent element.
                if (parentText !== linkText) {
                    return;
                }
            }

            var $iframe = buildIframe(embedUrl);

            // Replace the parent block if it is a simple <p> wrapper,
            // otherwise replace just the anchor itself.
            var tag = ($parent[0].tagName || '').toLowerCase();
            if (tag === 'p' || tag === 'div') {
                $parent.replaceWith($iframe);
            } else {
                $a.replaceWith($iframe);
            }
        });
    }

    return {
        /**
         * Entry point called by js_call_amd.
         *
         * @param {Object} opts
         * @param {string} opts.instanceUrl  PeerTube base URL.
         * @param {string} opts.embedBase    Embed URL prefix.
         * @param {string} opts.embedParams  Query string merged into every
         *                                    embed URL (no leading '?').
         */
        init: function(opts) {
            if (!opts || !opts.instanceUrl) {
                return;
            }
            var embedParams = opts.embedParams || '';
            $(document).ready(function() {
                processLinks(opts.instanceUrl, opts.embedBase, embedParams);
            });
        }
    };
});
