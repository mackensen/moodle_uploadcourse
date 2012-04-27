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
 * Strings for component 'tool_uploadcourse', language 'en', branch 'MOODLE_22_STABLE'
 *
 * @package    tool
 * @subpackage uploadcourse
 * @copyright  2012 Charles Fulton
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['brokencategory'] = 'Failed to create category {$a->name}. {$a->subcategories} were skipped';
$string['brokencourse'] = 'Failed to create course {$a}';
$string['creationstatus'] = 'Course creation status report:
<br />
<ul>
<li>Parsed {$a->bulk} courses from file</li>
<li>Created {$a->created} courses</li>
<li>Created {$a->catcreated} categories</li>
<li>Skipped {$a->skipped} courses</li>
<li>Failed to create {$a->broken} courses because of errors</li>
<li>Failed to create {$a->catbroken} categories because of errors</li>
</ul>
<br />
Courses were re-sorted automatically. If new categories were created you should manually re-sort the categories. It is recommended that you run ANALYZE and OPTIMIZE (or equivalent for your DBMS) on the course, course_categories, course_sections tables.';
$string['defaultgeneral'] = 'Default: General';
$string['defaultgroups'] = 'Default: Groups';
$string['defaultavailability'] = 'Default: Availability';
$string['defaultlanguage'] = 'Default: Language';
$string['defaultroles'] = 'Default: Role renaming';
$string['invalidcategory'] = 'Failed to create course {$a} because of invalid parent categories';
$string['nocourses'] = 'Did not find any courses to upload';
$string['parsedcourses'] = 'Parsed {$a} courses from file';
$string['pluginname'] = 'Course upload';
$string['skippedcourses'] = '{$a} three courses were skipped (duplicate shortname)';
$string['summaryformat'] = 'Summary format';
$string['uploadcourses'] = 'Upload courses';
$string['uploadcourses_help'] = 'Upload an <a href="http://www.rfc-editor.org/rfc/rfc4180.txt" target="_blank">RFC4180</a>-Compliant CSV file.

See <a href="readme.html" target="_blank">here</a> for a full list of valid fields.';
$string['utility'] = 'Utility';
?>