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
 * Bulk user registration functions
 *
 * @package    tool
 * @subpackage uploadcourse
 * @copyright  2004 onwards Martin Dougiamas (http://dougiamas.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Validate input during csv processing and fix as appropriate
 *
 * @param mixed $value
 * @param string $key
 * @param moodle_url $returnurl
 * @param integer $linenum
 */
function uc_validate_item($value, $key, $returnurl, $linenum) {
    switch($key) {
        case 'category':
            if (is_int($value)) {
                if (uc_category_exists($value)) {
                    return $value;
                } else {
                    print_error('erroronline', 'error', $returnurl, $linenum);
                }
            } else if (is_string($value)) {
                // String path
                $value = clean_param($value, PARAM_PATH);	// clean
                $categories = explode('/', $value);
                array_walk($categories, 'trim');
                return $categories;
            } else {
                // Must be a string or integer
                print_error('erroronline', 'error', $returnurl, $linenum);
            }
            break;
        case 'startdate':
            if (is_int($value)) {
                return $value;
            } else {
                return strtotime($value);
            }
            break;
        default:
            switch (gettype($value)) {
                case 'boolean':
                    return clean_param($value, PARAM_BOOLEAN);
                    break;
                case 'integer':
                    return clean_param($value, PARAM_INT);
                    break;
                case 'double':
                    return clean_param($value, PARAM_FLOAT);
                    break;
                case 'string':
                default:
                    return clean_param($value, PARAM_TEXT);
                    break;
            }
            break;
    }
}

/**
 * Validation callback function - verified the column line of the csv field.
 * Derived from uu_validate_user_upload_columns() in tool/uploaduser
 * This will fail without a valid header row and at least one row of data
 **/
function uc_validate_course_upload_columns(csv_import_reader $cir, $stdfields, moodle_url $returnurl) {
    $columns = $cir->get_columns();

    // Verify that we have a header row AND at least one row of data
    if (empty($columns)) {
        $cir->close();
        $cir->cleanup();
        print_error('cannotreadtmpfile', 'error', $returnurl);
    } else if(count($columns) < 2) {
        $cir->close();
        $cir->cleanup();
        print_error('csvfewcolumns', 'error', $returnurl);
    }

    // Textlib for handling unicode data
    $textlib = textlib_get_instance();

    // Cycle through the columns. Note that $value is unused.
    $processed = array();
    foreach ($columns as $key => $value) {
        $field    = $columns[$key];
        $field_lc = $textlib->strtolower($field);

        // Fields should be lower-cased. Check for presence in $stdfields and normalize
        if (in_array($field, $stdfields) || in_array($field_lc, $stdfields)) {
            $field_new = $field_lc;
        } else {
            $cir->close();
            $cir->cleanup();
            print_error('invalidfieldname', 'error', $returnurl, $field);
        }

        // Check for duplicates
        if (in_array($field_new, $processed)) {
            $cir->close();
            $cir->cleanup();
            print_error('duplicatefieldname', 'error', $returnurl, $field_new);
        }

        $processed[$key] = $field_new;
    }
    return $processed;
}

/**
 * Checks if the given category id exists
 *
 * @param integer $category
 * @return boolean
 */
function uc_category_exists($category) {
    global $DB;
    return $DB->record_exists('course_categories', array('id' => $category));
}

/**
 * Finds the category with the given name and parent, or create it if it doesn't exist. Returns
 * the category ID. Also sets the $hstatus variable:
 *  -1 : Failed to create category
 *   1 : Category found
 *   2 : Created new category
 *
 * @param string $hname
 * @param integer $hstatus
 * @param integer $hparent
 * @param array $categories_cached
 * @return integer
 */
function uc_category_create($hname, &$hstatus, $hparent=0, &$categories_cached) {
    global $DB;

    // Does this category exist?
    $hash = md5($hname . $hparent);
    if (array_key_exists($hash, $categories_cached)) {
        $hstatus = 1;
        return $categories_cached[$hash];
    } else if ($result = $DB->get_record('course_categories', array('name' => $hname, 'parent' => $hparent))) {
        $hstatus = 1;
        $categories_cached[$hash] = $result->id;
        return $result->id;
    } else {
        $data = new stdClass();
        $data->name = $hname;
        $data->parent = $hparent;
        if ($id = $DB->insert_record('course_categories', $data, true)) {
            $hstatus = 2;
            $categories_cached[$hash] = $id;
            return $id;
        } else {
            $hstatus = -1;
            return -1;
        }
    }
}

/**
 * Checks the existence of a course shortname
 * @param string $shortname
 * @return boolean
 */
function uc_course_shortname_exists($shortname) {
    global $DB;
    return $DB->record_exists('course', array('shortname' => $shortname)); // Check shortname is unique before inserting
}

/**
 * Attempts to create a course. $coursedata contains the default course object
 *
 * @param integer $category
 * @param array $course
 * @param array $headers
 * @param object $coursedata
 *
 * @return mixed
 */
function uc_create_course($category, $course, $headers, $coursedata) {
    global $DB;

    if(!is_array($course) || !is_array($headers)) {
        return false;
    }

    // Add category
    $course['category'] = $category;

    // Add items
    foreach ($headers as $key => $value) {
        if (!empty($course[$value])) {
            $coursedata->{$value} = $course[$value];
        }
    }

    $newcourse = create_course($coursedata);

    return true;
}

/**
 * Creates a status object to hold all the counters.
 * @param array $courses
 * @return object
 */
function uc_create_status_object($courses) {
    $status             = new stdClass();
    $status->bulk       = count($courses);
    $status->skipped    = 0;
    $status->created    = 0;
    $status->broken     = 0;
    $status->read       = 0;
    $status->catcreated = 0;
    $status->catbroken  = 0;
    return $status;
}
?>
