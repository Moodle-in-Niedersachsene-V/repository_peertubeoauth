<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Tests for the channel naming helpers of repository_peertubeoauth.
 *
 * @package    repository_peertubeoauth
 * @author     Moodle in Niedersachsen e. V.
 * @copyright  2026 Moodle in Niedersachsen e. V.
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace repository_peertubeoauth;

use PHPUnit\Framework\Attributes\CoversClass;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/repository/peertubeoauth/lib.php');

/**
 * Tests for build_channel_handle_for_user and its display name counterpart.
 *
 * The cases follow the situation on the shared LTI hub. An LTI account
 * without name transfer carries the user id of its home school as the
 * first name and the address of that school as the last name, which is
 * what the pseudonym setting exists for.
 */
#[CoversClass(\repository_peertubeoauth::class)]
final class channel_handle_test extends \advanced_testcase {
    /** @var string School code used throughout these tests. */
    const SCHOOLCODE = 'hub';

    /** @var string Last name an LTI account carries without name transfer. */
    const LTI_LASTNAME = 'https://mood531.moodle-ni.de';

    /**
     * Build a user record without touching the database.
     *
     * The helpers under test read four fields only, so a plain object
     * keeps each case readable and independent of the user generator.
     *
     * @param int $id Moodle user id.
     * @param string $auth Authentication plugin name.
     * @param string $firstname First name of the account.
     * @param string $lastname Last name of the account.
     * @return \stdClass The user record.
     */
    private function make_user(
        int $id,
        string $auth,
        string $firstname = '',
        string $lastname = ''
    ): \stdClass {
        return (object)[
            'id' => $id,
            'auth' => $auth,
            'firstname' => $firstname,
            'lastname' => $lastname,
        ];
    }

    /**
     * A local account keeps its name in the handle and the display name.
     *
     * @return void
     */
    public function test_local_account_is_named_by_default(): void {
        $this->resetAfterTest();

        $user = $this->make_user(4711, 'manual', 'Maria', 'Mueller');

        $this->assertSame(
            'gms_muster_mueller_maria',
            \repository_peertubeoauth::build_channel_handle_for_user($user, 'gms_muster')
        );
        $this->assertSame(
            'Maria Mueller (GMS_MUSTER)',
            \repository_peertubeoauth::build_channel_displayname_for_user($user, 'gms_muster')
        );
    }

    /**
     * Umlauts and hyphens are mapped to what PeerTube accepts.
     *
     * @return void
     */
    public function test_named_handle_replaces_umlauts_and_hyphens(): void {
        $this->resetAfterTest();

        $user = $this->make_user(4711, 'manual', 'Jörg', 'Müller-Schmidt');

        $this->assertSame(
            'gms_muster_mueller_schmidt_joerg',
            \repository_peertubeoauth::build_channel_handle_for_user($user, 'gms_muster')
        );
    }

    /**
     * An LTI account is pseudonymised without the administrator acting.
     *
     * @return void
     */
    public function test_lti_account_is_pseudonymised_by_default(): void {
        $this->resetAfterTest();

        $user = $this->make_user(4711, 'lti', '57', self::LTI_LASTNAME);
        $handle = \repository_peertubeoauth::build_channel_handle_for_user($user, self::SCHOOLCODE);

        $this->assertSame('hub_u4711', $handle);
        $this->assertStringNotContainsString('mood531', $handle);
    }

    /**
     * Setting the mode to LTI only leaves local accounts named.
     *
     * @return void
     */
    public function test_lti_mode_leaves_local_accounts_named(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_LTI, 'peertubeoauth');

        $user = $this->make_user(4711, 'manual', 'Maria', 'Mueller');

        $this->assertSame(
            'hub_mueller_maria',
            \repository_peertubeoauth::build_channel_handle_for_user($user, self::SCHOOLCODE)
        );
    }

    /**
     * Switching the setting off reveals the school in an LTI handle.
     *
     * This is the behaviour the pseudonym setting replaces, so the test
     * asserts the leak rather than the exact mangled string.
     *
     * @return void
     */
    public function test_setting_off_reveals_the_school_for_lti_accounts(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_OFF, 'peertubeoauth');

        $user = $this->make_user(4711, 'lti', '57', self::LTI_LASTNAME);
        $handle = \repository_peertubeoauth::build_channel_handle_for_user($user, self::SCHOOLCODE);

        $this->assertStringContainsString('mood531', $handle);
        $this->assertNotSame('hub_u4711', $handle);
    }

    /**
     * Switching the setting off makes nameless accounts collide.
     *
     * Two LTI accounts of one school share a single channel then, which
     * is the second reason the setting defaults to LTI only.
     *
     * @return void
     */
    public function test_setting_off_collapses_nameless_accounts_onto_one_channel(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_OFF, 'peertubeoauth');

        $first = $this->make_user(4711, 'lti');
        $second = $this->make_user(4712, 'lti');

        $this->assertSame(
            \repository_peertubeoauth::build_channel_handle_for_user($first, self::SCHOOLCODE),
            \repository_peertubeoauth::build_channel_handle_for_user($second, self::SCHOOLCODE)
        );
    }

    /**
     * Mode all pseudonymises a local account as well.
     *
     * @return void
     */
    public function test_mode_all_pseudonymises_local_accounts(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_ALL, 'peertubeoauth');

        $user = $this->make_user(4711, 'manual', 'Maria', 'Mueller');
        $handle = \repository_peertubeoauth::build_channel_handle_for_user($user, self::SCHOOLCODE);

        $this->assertSame('hub_u4711', $handle);
        $this->assertStringNotContainsString('mueller', $handle);
    }

    /**
     * A pseudonym handle works without a school code.
     *
     * @return void
     */
    public function test_pseudonym_handle_without_school_code(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_ALL, 'peertubeoauth');

        $user = $this->make_user(4711, 'manual');

        $this->assertSame(
            'u4711',
            \repository_peertubeoauth::build_channel_handle_for_user($user, '')
        );
    }

    /**
     * A handle never exceeds the length PeerTube accepts.
     *
     * @return void
     */
    public function test_handles_stay_within_the_peertube_limit(): void {
        $this->resetAfterTest();

        $limit = \repository_peertubeoauth::MAX_HANDLE_LENGTH;
        $longdomain = 'https://sehr-lange-schuldomain-beispiel.moodle-ni.de';
        $longcode = str_repeat('a', 48);

        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_OFF, 'peertubeoauth');
        $named = \repository_peertubeoauth::build_channel_handle_for_user(
            $this->make_user(4711, 'lti', '57', $longdomain),
            self::SCHOOLCODE
        );
        $this->assertLessThanOrEqual($limit, strlen((string)$named));

        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_ALL, 'peertubeoauth');
        $pseudonym = \repository_peertubeoauth::build_channel_handle_for_user(
            $this->make_user(4711, 'manual'),
            $longcode
        );
        $this->assertLessThanOrEqual($limit, strlen((string)$pseudonym));
    }

    /**
     * Shortening a pseudonym handle still keeps two users apart.
     *
     * The user id carries the identity, so a long school code is cut
     * instead of the id. Were it the other way round, two teachers of
     * one school would end up sharing a channel.
     *
     * @return void
     */
    public function test_shortened_pseudonym_handles_stay_distinct(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_ALL, 'peertubeoauth');

        $longcode = str_repeat('a', 48);
        $first = \repository_peertubeoauth::build_channel_handle_for_user(
            $this->make_user(4711, 'manual'),
            $longcode
        );
        $second = \repository_peertubeoauth::build_channel_handle_for_user(
            $this->make_user(4712, 'manual'),
            $longcode
        );

        $this->assertNotSame($first, $second);
        $this->assertStringEndsWith('u4711', $first);
        $this->assertStringEndsWith('u4712', $second);
    }

    /**
     * Every handle uses only the characters PeerTube accepts.
     *
     * @return void
     */
    public function test_handles_use_only_accepted_characters(): void {
        $this->resetAfterTest();

        $user = $this->make_user(4711, 'lti', '57', self::LTI_LASTNAME);

        foreach (
            [
            \repository_peertubeoauth::PSEUDONYM_OFF,
            \repository_peertubeoauth::PSEUDONYM_LTI,
            \repository_peertubeoauth::PSEUDONYM_ALL,
            ] as $mode
        ) {
            set_config('pseudonymchannels', $mode, 'peertubeoauth');
            $handle = \repository_peertubeoauth::build_channel_handle_for_user($user, 'GMS Müster-Süd');

            $this->assertMatchesRegularExpression(
                '/^[a-z0-9_\.]+$/',
                (string)$handle,
                'Mode ' . $mode . ' produced a handle PeerTube would reject.'
            );
        }
    }

    /**
     * A handle is refused when nothing usable is left to build it from.
     *
     * @return void
     */
    public function test_handle_is_null_when_nothing_usable_remains(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_OFF, 'peertubeoauth');

        $user = $this->make_user(4711, 'manual', '///', '///');

        $this->assertNull(
            \repository_peertubeoauth::build_channel_handle_for_user($user, '')
        );
    }

    /**
     * A pseudonym display name carries the user id instead of the name.
     *
     * @return void
     */
    public function test_pseudonym_display_name_carries_no_name(): void {
        $this->resetAfterTest();

        $user = $this->make_user(4711, 'lti', '57', self::LTI_LASTNAME);
        $displayname = \repository_peertubeoauth::build_channel_displayname_for_user(
            $user,
            self::SCHOOLCODE
        );

        $this->assertStringContainsString('4711', $displayname);
        $this->assertStringContainsString('(HUB)', $displayname);
        $this->assertStringNotContainsString('mood531', $displayname);
    }

    /**
     * A display name without a school code carries no bracketed suffix.
     *
     * @return void
     */
    public function test_display_name_without_school_code_has_no_suffix(): void {
        $this->resetAfterTest();
        set_config('pseudonymchannels', \repository_peertubeoauth::PSEUDONYM_OFF, 'peertubeoauth');

        $user = $this->make_user(4711, 'manual', 'Maria', 'Mueller');

        $this->assertSame(
            'Maria Mueller',
            \repository_peertubeoauth::build_channel_displayname_for_user($user, '')
        );
    }
}
