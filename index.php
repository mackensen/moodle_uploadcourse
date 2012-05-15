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
 * Bulk course creation script from a comma separated file
 *
 * @package    tool
 * @subpackage uploadcourse
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @author     Rory Alford    (initial script)
 * @author     Ashley Gooding (2007 update)
 * @author     Cole Spicer    (2007 update)
 * @author     Mark Johnson   (Moodle 2 compatibility)
 * @author     Charles Fulton (admin tool integration)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

    require_once('../../../config.php');
    require_once($CFG->libdir.'/adminlib.php');
    require_once($CFG->libdir.'/blocklib.php');
    require_once($CFG->libdir.'/csvlib.class.php');
    require_once($CFG->dirroot.'/course/lib.php');
    require_once($CFG->dirroot.'/lib/uploadlib.php');
    require_once('locallib.php');
    require_once('course_form.php');
    $courseid=-1;

    require_login();
    admin_externalpage_setup('tooluploadcourse');
    require_capability('moodle/site:uploadusers', get_context_instance(CONTEXT_SYSTEM));

    set_time_limit(300); // Up the php timeout
    $returnurl = new moodle_url('/admin/tool/uploadcourse/index.php');

/// If a file has been uploaded, then process it
    $mform = new admin_uploadcourse_form();
    if ($formdata = $mform->get_data()) {
        echo $OUTPUT->header();

        // Required fields
        $REQUIRED_FIELDS = array('fullname', 'shortname');

        // Form fields
        $FORM_FIELDS = array('format', 'numsections', 'startdate', 'hiddensections', 'newsitems',
                            'showgrades', 'showreports', 'maxbytes', 'groupmode', 'groupmodeforce',
                            'defaultgroupingid', 'visible', 'lang', 'summaryformat');

        // Allowed fields
        $ALLOWED_FIELDS = array('fullname', 'shortname','category','sortorder','summary', 'summaryformat',
                            'format','showgrades','newsitems','startdate','numsections','maxbytes','visible','groupmode',
                            'timecreated','timemodified','idnumber','password','enrolperiod',
                            'groupmodeforce','lang','theme','cost','showreports',
                            'guest','enrollable','enrolstartdate','enrolenddate','notifystudents',
                            'template','expirynotify','expirythreshold','hiddensections','defaultgroupingid');

        // Populate the default course
        $default_course = new stdClass();
        foreach($FORM_FIELDS as $key) {
            $default_course->{$key} = $formdata->{$key};
        }

        // Import the CSV from file
        $iid = csv_import_reader::get_new_iid('uploadcourse');
        $cir = new csv_import_reader($iid, 'uploadcourse');
        $content = $mform->get_file_content('coursefile');
        $cir->load_csv_content($content, $formdata->encoding, $formdata->delimiter_name);

        // Process the column headers
        $headers = uc_validate_course_upload_columns($cir, $ALLOWED_FIELDS, $returnurl);

        // Check for required values
        $missing_required_values = array_diff($REQUIRED_FIELDS, $headers);
        if (!empty($missing_required_values)) {
            print_error('fieldrequired', 'error', $returnurl, implode(',',array_keys($missing_required_values)));
        }

        // Set variables for bulk processing
        $cir->init();
        $linenum                 = 1;
        $fieldcount              = count($headers);
        $bulkcourses             = array();
        $categories_cached       = array();	// store created categories

        while ($line = $cir->next()) {
            $linenum++;

            // Quit if irregular number of columns
            if (count($line) > $fieldcount) {
                $cir->close();
                $cir->cleanup();
                print_error('erroronline', 'error', $returnurl, $linenum);
            }

            // Process line
            $coursetocreate = array();
            foreach($line as $key => $value) {
                $cf = $headers[$key];

                // Pass each item through validation
                $coursetocreate[$cf] = uc_validate_item($value, $cf, $returnurl, $linenum);
            }

            $bulkcourses[] = $coursetocreate; // Merge into array
        }

        // Done with csv import
        $cir->close();
        $cir->cleanup();

        // Create status object
        $status = uc_create_status_object($bulkcourses);

        // Terminate if no courses found
        if (empty($status->bulk)) {
            print_error('nocourses', 'tool_uploadcourses', $returnurl);
        }

        // Loop through processed courses
        foreach ($bulkcourses as $bulkcourse) {
            // Try to create the course
            if (!uc_course_shortname_exists($bulkcourse['shortname'])) {

                $coursetocategory = 0; // Category ID

                if (is_array($bulkcourse['category'])) {
                    // Course Category creation routine as a category path was given
                    $curparent=0;
                    $curstatus=0;

                    foreach ($bulkcourse['category'] as $catindex => $catname) {
                        $curparent = uc_category_create($catname,$curstatus,$curparent,$categories_cached);
                        switch ($curstatus) {
                          case 1: // Skipped the category, already exists
                              break;
                          case 2: // Created a category
                              $status->catcreated++;
                              break;
                          default:
                              $status->catbroken += count($bulkcourse['category']) - $catindex;
                              $failed_message = new stdClass();
                              $failed_message->name = $catname;
                              $failed_message->subcategories = count($bulkcourse['category']) - $catindex - 1;
                              $coursetocategory = -1;
                              notify(get_string('brokencategory', 'tool_uploadcourse', $failed_message), 'notifyproblem');
                          break 2;
                        }
                    }
                    ($coursetocategory==-1) or $coursetocategory = $curparent;
                    // Last category created will contain the actual course
                } else {
                    // It's just a straight category ID
                    $coursetocategory = $bulkcourse['category'];
                }

                if ($coursetocategory == -1) {
                    notify(get_string('invalidcategory', 'tool_uploadcourse', $bulkcourse['shortname']), 'notifyproblem');
                } else {
                    if (uc_create_course($coursetocategory, $bulkcourse, $headers, $default_course)) {
                        $status->created++;
                    } else {
                        $status->broken++;
                        notify(get_string('brokencourse', 'tool_uploadcourse', $bulkcourse['shortname']), 'notifyproblem');
                    }
              }
            } else {
              // Skip course, already exists
              $status->skipped++;
            }
            $status->read++;
        }

        // Course creation complete; re-sort
        fix_course_sortorder();

        // Print results
        notify(get_string('creationstatus', 'tool_uploadcourse', $status), 'notifysuccess');
        echo $OUTPUT->footer();

    } else {
        // No form submitted; display interface
        echo $OUTPUT->header();
        echo $OUTPUT->heading_with_help(get_string('uploadcourses', 'tool_uploadcourse'), 'uploadcourses', 'tool_uploadcourse');
        $mform->display();
        echo $OUTPUT->footer();
    }
?>
