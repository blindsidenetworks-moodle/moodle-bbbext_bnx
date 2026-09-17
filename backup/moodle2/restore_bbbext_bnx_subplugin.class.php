<?php
// This file is part of Moodle - http://moodle.org/
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
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Restore support for the BN Experience subplugin.
 *
 * @package   bbbext_bnx
 * @copyright 2025 onwards, Blindside Networks Inc
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author    Jesus Federico  (jesus [at] blindsidenetworks [dt] com)
 */
class restore_bbbext_bnx_subplugin extends restore_subplugin {
    /**
     * Declare the paths handled by this subplugin during restore.
     *
     * @return restore_path_element[]
     */
    protected function define_bigbluebuttonbn_subplugin_structure() {
        $paths = [];

        $paths[] = new restore_path_element(
            $this->get_namefor(''),
            $this->get_pathfor('/bbbext_bnx')
        );

        $paths[] = new restore_path_element(
            $this->get_namefor('bnxsetting'),
            $this->get_pathfor('/bbbext_bnx/bbbext_bnx_settings')
        );

        $paths[] = new restore_path_element(
            $this->get_namefor('presentation'),
            $this->get_pathfor('/bbbext_bnx/bbbext_bnx_presentations/bbbext_bnx_presentation_file')
        );

        $paths[] = new restore_path_element(
            $this->get_namefor('reminder'),
            $this->get_pathfor('/bbbext_bnx_reminders')
        );

        $paths[] = new restore_path_element(
            $this->get_namefor('reminderguest'),
            $this->get_pathfor('/bbbext_bnx_reminders_guests')
        );

        return $paths;
    }

    /**
     * Persist the BN Experience record for a restored activity.
     *
     * @param array $data
     */
    public function process_bbbext_bnx($data) {
        global $DB;

        $data = (object) $data;
        $data->bigbluebuttonbnid = $this->get_new_parentid('bigbluebuttonbn');

        if (empty($data->timecreated)) {
            $data->timecreated = time();
        }

        if (empty($data->timemodified)) {
            $data->timemodified = $data->timecreated;
        }

        $newid = $DB->insert_record('bbbext_bnx', $data);
        $this->set_mapping('bbbext_bnx', $data->id, $newid);
    }

    /**
     * Persist the BN Experience setting records for a restored activity.
     *
     * @param array $data
     */
    public function process_bbbext_bnx_bnxsetting($data) {
        global $DB;

        $data = (object) $data;
        $data->bnxid = $this->get_new_parentid('bbbext_bnx');

        if (empty($data->bnxid)) {
            // Parent record not restored (unlikely but safe guard).
            return;
        }

        if (empty($data->timemodified)) {
            $data->timemodified = time();
        }
        $DB->insert_record('bbbext_bnx_settings', $data);
    }

    /**
     * Restore one presentation record for the mapped BNX activity.
     *
     * @param array $data
     * @return void
     */
    public function process_bbbext_bnx_presentation($data): void {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;
        $data->bnxid = $this->get_new_parentid('bbbext_bnx');
        if (empty($data->bnxid)) {
            return;
        }

        // Stored file IDs are global and remain occupied by the source activity during restore.
        $data->fileid = -(int)$data->fileid;
        $newid = $DB->insert_record('bbbext_bnx_presentations', $data);
        $this->set_mapping('bbbext_bnx_presentation', $oldid, $newid);
    }

    /**
     * Restore a reminder timespan record.
     *
     * @param array $data
     */
    public function process_bbbext_bnx_reminder($data) {
        global $DB;

        $data = (object) $data;
        $data->bigbluebuttonbnid = $this->get_new_parentid('bigbluebuttonbn');
        $DB->insert_record('bbbext_bnx_reminders', $data);
    }

    /**
     * Restore a guest email record.
     *
     * @param array $data
     */
    public function process_bbbext_bnx_reminderguest($data) {
        global $DB;

        $data = (object) $data;
        $data->bigbluebuttonbnid = $this->get_new_parentid('bigbluebuttonbn');
        $data->usermodified = $this->get_mappingid('user', $data->usermodified);
        $data->userfrom = $this->get_mappingid('user', $data->userfrom);
        $data->timecreated = $this->apply_date_offset($data->timecreated);
        $data->timemodified = $this->apply_date_offset($data->timemodified);
        $newitemid = $DB->insert_record('bbbext_bnx_reminders_guests', $data);
        $this->set_mapping('bbbext_bnx_reminders_guests', $data->id, $newitemid);
    }

    /**
     * Restore presentation files and update their stored-file identifiers.
     *
     * @return void
     */
    public function after_execute_bigbluebuttonbn(): void {
        global $DB;

        $this->add_related_files('bbbext_bnx', 'presentation', null);

        $bnxid = $this->get_new_parentid('bbbext_bnx');
        $moduleid = $this->get_new_parentid('bigbluebuttonbn');
        $cm = empty($moduleid) ? false : get_coursemodule_from_instance('bigbluebuttonbn', $moduleid);
        if (empty($bnxid) || $cm === false) {
            return;
        }

        $files = get_file_storage()->get_area_files(
            \context_module::instance($cm->id)->id,
            'bbbext_bnx',
            'presentation',
            0,
            'itemid, filepath, filename',
            false
        );
        $filesbyname = [];
        foreach ($files as $file) {
            $filesbyname[$file->get_filename()] = $file;
        }

        foreach ($DB->get_records('bbbext_bnx_presentations', ['bnxid' => $bnxid]) as $presentation) {
            if (!isset($filesbyname[$presentation->filename])) {
                continue;
            }
            $presentation->fileid = $filesbyname[$presentation->filename]->get_id();
            $DB->update_record('bbbext_bnx_presentations', $presentation);
        }
    }
}
