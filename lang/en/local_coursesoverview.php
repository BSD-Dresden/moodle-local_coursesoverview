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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * English strings.
 *
 * @package    local_coursesoverview
 * @copyright  2026 BSD GmbH
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

$string['accesssince'] = 'Access since';
$string['applyfilters'] = 'Filter';
$string['backtooverview'] = 'Back to courses overview';
$string['colemail'] = 'Email address';
$string['colfirstname'] = 'First name';
$string['colfullname'] = 'First name and surname';
$string['colignore'] = 'Not needed';
$string['collastfirst'] = 'Surname, first name';
$string['collastname'] = 'Surname';
$string['colnamemail'] = 'Name and address in one cell';
$string['colrole'] = 'Role';
$string['completed_lastseen'] = 'Course completed / Last seen';
$string['completedparticipants'] = 'Completed Participants';
$string['completiondisabled'] = 'Completion tracking is disabled in this course.';
$string['completionrate'] = 'Completion rate (%)';
$string['completionstatus'] = 'Completion status';
$string['coursehidden'] = 'Course "{$a}" has been hidden.';
$string['courseshown'] = 'Course "{$a}" is visible again.';
$string['coursesoverview:createorder'] = 'Create an order together with its course';
$string['coursesoverview:view'] = 'View the courses overview';
$string['deletechecked'] = 'I have checked the list';
$string['deleteconfirm'] = 'Permanently delete the course and the accounts listed';
$string['deletedone'] = 'Course "{$a->course}" deleted, {$a->users} accounts removed.';
$string['deleteheading'] = 'Delete course: {$a}';
$string['deleteirreversible'] = 'Deleting cannot be undone. Check both lists.';
$string['deletenothidden'] = 'Only hidden courses can be deleted here. Hide the course first.';
$string['deletenousers'] = 'No account will be deleted, only the course.';
$string['deleteunchecked'] = 'Please confirm that you have checked the list.';
$string['deleteusers'] = 'These {$a} accounts will be permanently deleted';
$string['delimiterauto'] = 'Work it out';
$string['delimitercomma'] = 'Comma';
$string['delimitersemicolon'] = 'Semicolon';
$string['delimiterspace'] = 'Blanks';
$string['delimitertab'] = 'Tab';
$string['exportexcel'] = 'Export to Excel';
$string['keepadmin'] = 'Administrator';
$string['keepcourses'] = 'still enrolled in {$a} other courses';
$string['keepguest'] = 'Guest account';
$string['keeprole'] = 'Holds a role outside this course';
$string['keepself'] = 'Your own account';
$string['keepusers'] = 'These {$a} are kept';
$string['neworder'] = 'Create order';
$string['nocourses'] = 'No ongoing courses at the moment.';
$string['nocoursesmatch'] = 'No courses match the filter.';
$string['nocriteria'] = 'No completion criteria are defined for this course.';
$string['nogroup'] = 'No group';
$string['noparticipants'] = 'No participants.';
$string['notifybody'] = 'All {$a->total} participants have completed the course "{$a->fullname}" ({$a->shortname}).

Course end: {$a->enddate}

Participant list: {$a->url}
Excel export: {$a->exporturl}';
$string['notifyemails'] = 'Recipients of the completion notice';
$string['notifyemails_desc'] = 'Addresses notified once every participant of a course has completed. '
    . 'Separate several with commas, leave empty to switch the notice off. A Moodle account is not '
    . 'required, a shared mailbox will do.';
$string['notifysubject'] = 'Course fully completed: {$a}';
$string['orderabnr'] = 'Order number';
$string['orderback'] = 'Back';
$string['ordercheck'] = 'Check against Moodle';
$string['ordercheck_help'] = 'Every line has now been held against the existing accounts. Lines in red stop the order. Roles can '
    . 'still be changed here, then update.';
$string['ordercheckcount'] = '{$a->total} lines, {$a->blocked} of them stop the order.';
$string['ordercheckheading'] = 'Check';
$string['ordercolumn'] = 'Column {$a}';
$string['ordercolumns'] = 'What the columns hold';
$string['ordercolumns_help'] = 'The assignment below was guessed from the pasted list. Correct what it got wrong and update.';
$string['ordercompany'] = 'Company';
$string['ordercontinue'] = 'Continue';
$string['ordercourseexists'] = 'A course with the short name "{$a}" already exists.';
$string['ordercoursefailed'] = 'The course could not be created from its template.';
$string['ordercreate'] = 'Create course and accounts';
$string['ordercredentialsfile'] = 'Credentials';
$string['ordercredentialsgone'] = 'The list of credentials is no longer available. It is only kept for the session in which the '
    . 'order was created.';
$string['orderdelimiter'] = 'Separator';
$string['orderdelivery'] = 'Credentials';
$string['orderdelivery_help'] = 'Whether the credentials go to the addresses in the list or come back as a list to send by hand. A '
    . 'free choice, also when several people share one address.';
$string['orderdeliverydirect'] = 'Send to the participants';
$string['orderdeliverylist'] = 'Hand back as a list';
$string['orderdirectqueued'] = 'The credentials go out with the next run of the scheduled task that mails new passwords.';
$string['orderdone'] = 'Course "{$a->course}" created, {$a->created} accounts created, {$a->reused} existing accounts enrolled.';
$string['orderdownload'] = 'Download credentials as Excel';
$string['orderduplicateemail'] = 'This address appears {$a} times in the list.';
$string['orderduplicateusername'] = 'User name "{$a}" appears more than once in this list.';
$string['orderenddate'] = 'Course end';
$string['ordererrorabnr'] = 'The order number is missing.';
$string['ordererrorcategory'] = 'Module "{$a}" has no category, and its template course does not supply one either.';
$string['ordererrorcompany'] = 'The company name is missing, or holds nothing a course short name can carry.';
$string['ordererrordate'] = 'Start and end both have to be dates.';
$string['ordererrorlist'] = 'The participant list is empty.';
$string['ordererrormodule'] = 'Please pick a module.';
$string['ordererrorrange'] = 'The course cannot end before it starts.';
$string['ordererrortemplate'] = 'No course with the short name "{$a}" exists, so there is nothing to copy.';
$string['orderexists'] = 'User name "{$a->username}" already belongs to an account: {$a->name}, created {$a->created}.';
$string['orderexistscourses'] = 'Enrolled in: {$a}.';
$string['orderfullname'] = 'Full name';
$string['orderfuturelater'] = 'A start in the future is not switched on yet, because the credentials would go out today. Set the '
    . 'start to today or hand the order over later.';
$string['ordergotocourse'] = 'Go to the course';
$string['orderhasheader'] = 'First line names the columns';
$string['orderinvalidemail'] = '"{$a}" is not an address.';
$string['orderlist'] = 'Participant list';
$string['orderlist_help'] = 'Paste the list as the customer sent it, one person per line.';
$string['orderlistready'] = 'The passwords below are shown only now and are not stored. Download the list before leaving this '
    . 'page.';
$string['ordermailtaken'] = 'This address is already used by {$a} other accounts.';
$string['ordermissingemail'] = 'Address missing.';
$string['ordermissingemailcolumn'] = 'No column holds the email address.';
$string['ordermissingname'] = 'Name incomplete.';
$string['ordermissingnamecolumn'] = 'No column holds the surname.';
$string['ordermodule'] = 'Module';
$string['ordermodules'] = 'Modules that can be ordered';
$string['ordermodules_desc'] = 'One module per line, four fields separated by a vertical bar: name, short name of the template '
    . 'course, category id, prefix of the generated course short name. An empty category id puts the new course where its template '
    . 'sits.<br />Example: <code>Grundmodul | OJU_Vorlage_GM | 12 | OJU_GM</code>';
$string['ordermonths'] = 'Runtime in months';
$string['ordermonths_desc'] = 'Prefills the course end when an order is created, and can be changed per order.';
$string['ordernolines'] = 'Nothing could be read from the pasted list.';
$string['ordernomanualenrol'] = 'The new course has no manual enrolment, so nobody could be enrolled.';
$string['ordernomodules'] = 'No module has been configured yet. An order needs at least one.';
$string['ordernopeople'] = 'The list yielded nobody. Check the separator and the assignment.';
$string['ordernotcreated'] = 'Account "{$a}" was not created.';
$string['ordernotes'] = 'Notes';
$string['ordernousername'] = 'No user name can be made from this line.';
$string['orderpattern'] = 'Pattern for the full course name';
$string['orderpattern_desc'] = 'Placeholders: {module}, {company}, {abnr}, {shortname}. Leave empty to use the short name for '
    . 'both.';
$string['orderpeople'] = 'What was read';
$string['orderpeoplecount'] = '{$a->total} people, {$a->flawed} of them with notes.';
$string['orderreuse'] = 'Use the existing account and only enrol it';
$string['orderreused'] = 'The existing account is used again and gets no new credentials.';
$string['orderreusehint'] = 'Only an organiser may keep an existing account.';
$string['orderrow'] = 'Line';
$string['orderruntime'] = 'Runtime';
$string['orderscheme'] = 'User names';
$string['orderscheme_help'] = 'The address is the simpler choice, but it only works once per person. A shared mailbox therefore '
    . 'needs firstname.lastname.';
$string['orderschemeemail'] = 'Email address';
$string['orderschemename'] = 'firstname.lastname';
$string['ordershortname'] = 'Course short name';
$string['orderstartdate'] = 'Course start';
$string['orderstartdate_help'] = 'A start in the future holds back the enrolment, and the credentials with it.';
$string['orderstartpassword'] = 'Start password';
$string['orderupdate'] = 'Update';
$string['organiser'] = 'Organiser';
$string['organisers'] = 'Organisers';
$string['overview'] = 'Courses overview';
$string['pluginname'] = 'Online instructions';
$string['progressheader'] = 'Sections completed / total';
$string['roleboth'] = 'Organiser and participant';
$string['roleorganiser'] = 'Organiser';
$string['roleparticipant'] = 'Participant';
$string['settings'] = 'Online instructions: settings';
$string['statusactive'] = 'Ongoing';
$string['statuscompleted'] = 'Completed';
$string['statusendingsoon'] = 'Ending soon, not completed';
$string['statushidden'] = 'Hidden (settled)';
$string['statusinprogress'] = 'Started, not completed';
$string['statusnotstarted'] = 'Not started';
$string['statusoverdue'] = 'Ended, not completed';
$string['statuspast'] = 'Finished';
$string['statustodo'] = 'Needs attention';
$string['statustosettle'] = 'Completed, not yet settled';
$string['tasknotifycompleted'] = 'Notify about fully completed courses';
$string['totalparticipants'] = 'Total Participants';
$string['withoutparticipants'] = 'Show courses without participants';
