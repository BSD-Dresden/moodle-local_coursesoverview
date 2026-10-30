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
$string['correct'] = 'Correct';
$string['correctafter'] = 'After';
$string['correctback'] = 'Back to the participants';
$string['correctbacklist'] = 'Back to the people of the order';
$string['correctbefore'] = 'Before';
$string['correctcheck'] = 'Check';
$string['correctheading'] = 'Correct details: {$a}';
$string['correctheld'] = 'The credentials still go out as planned on {$a->date} at {$a->time}, to the corrected address.';
$string['correctlist_help'] = 'Everybody this order created or enrolled. A wrongly sent name or address is corrected here without '
    . 'deleting the account: enrolment, groups and progress stay as they are.';
$string['correctloggedin'] = 'This person has logged in before and will have to use the new user name {$a} from now on.';
$string['correctmailed'] = 'New credentials sent to {$a}.';
$string['correctmailfailed'] = 'The mail could not be sent. The hourly task will try again.';
$string['correctnothing'] = 'Nothing has changed, and no new credentials are to go out.';
$string['correctpassword'] = 'The password below is shown only now and is not stored. Pass it on before leaving the page.';
$string['correctpeople'] = 'Correct details';
$string['correctpersonheading'] = 'Correct details of {$a}';
$string['correctresend'] = 'Credentials';
$string['correctresend_help'] = 'New credentials replace the password the person was given. Keep the old ones only if they have '
    . 'arrived.';
$string['correctresenddirect'] = 'Send new credentials to the corrected address';
$string['correctresendlist'] = 'Show new credentials here';
$string['correctresendnone'] = 'Keep the credentials';
$string['correctreused'] = 'The account existed before this order and gets no new credentials here.';
$string['correctsave'] = 'Save';
$string['correctsaved'] = 'Details of {$a} corrected.';
$string['correctstate'] = 'Credentials';
$string['correctstatedue'] = 'Not yet sent';
$string['correctstatelist'] = 'Handed back as a list';
$string['correctstatereused'] = 'Existing account';
$string['correctstatesent'] = 'Sent on {$a}';
$string['correctstatewaiting'] = 'Waiting for {$a->date}, {$a->time}';
$string['correcttaken'] = 'The user name {$a} already belongs to another account.';
$string['coursehidden'] = 'Course "{$a}" has been hidden.';
$string['courseshown'] = 'Course "{$a}" is visible again.';
$string['coursesoverview:createorder'] = 'Create an order together with its course';
$string['coursesoverview:view'] = 'View the courses overview';
$string['deletechecked'] = 'I have checked the list';
$string['deleteconfirm'] = 'Permanently delete the course and the accounts listed';
$string['deletedone'] = 'Course "{$a->course}" deleted, {$a->users} accounts removed.';
$string['deletedone_one'] = 'Course "{$a->course}" deleted, 1 account removed.';
$string['deleteheading'] = 'Delete course: {$a}';
$string['deleteirreversible'] = 'Deleting cannot be undone. Check both lists.';
$string['deletenothidden'] = 'Only hidden courses can be deleted here. Hide the course first.';
$string['deletenousers'] = 'No account will be deleted, only the course.';
$string['deleteunchecked'] = 'Please confirm that you have checked the list.';
$string['deleteusers'] = 'These {$a} accounts will be permanently deleted';
$string['deleteusers_one'] = 'This account will be permanently deleted';
$string['delimiterauto'] = 'Work it out';
$string['delimitercomma'] = 'Comma';
$string['delimitersemicolon'] = 'Semicolon';
$string['delimiterspace'] = 'Blanks';
$string['delimitertab'] = 'Tab';
$string['exportexcel'] = 'Export to Excel';
$string['keepadmin'] = 'Administrator';
$string['keepcourses'] = 'still enrolled in {$a} other courses';
$string['keepcourses_one'] = 'still enrolled in one other course';
$string['keepguest'] = 'Guest account';
$string['keeprole'] = 'Holds a role outside this course';
$string['keepself'] = 'Your own account';
$string['keepusers'] = 'These {$a} are kept';
$string['keepusers_one'] = 'This one is kept';
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
$string['orderadd_help'] = 'Course, company, order number and user names come from the order. How the credentials of the people '
    . 'reported late go out can be chosen anew.';
$string['orderaddcreate'] = 'Create accounts and enrol';
$string['orderaddeddone'] = 'Reported late to "{$a->course}". New accounts: {$a->created}, existing accounts enrolled: '
    . '{$a->reused}.';
$string['orderaddheading'] = 'Report participants late: {$a}';
$string['orderaddpeople'] = 'Report participants late';
$string['orderallskipped'] = 'Every line is left out, there is nothing to create.';
$string['orderalreadyincourse'] = '{$a} is already enrolled in this course.';
$string['orderback'] = 'Back';
$string['orderbox'] = 'Order';
$string['orderboxinfo'] = '{$a->company}, order number {$a->abnr}, created {$a->created}.';
$string['orderboxwaiting'] = 'Credentials for {$a->count} people are waiting for {$a->date}, {$a->time}.';
$string['orderboxwaiting_one'] = 'Credentials for 1 person are waiting for {$a->date}, {$a->time}.';
$string['ordercheck'] = 'Check against Moodle';
$string['ordercheck_help'] = 'Every line has now been checked against the existing accounts. Red lines stop the order. Roles can '
    . 'still be changed here, and lines that are not meant to be created can be left out; the order takes both into account '
    . 'straight away.';
$string['ordercheckcount'] = '{$a->total} lines, {$a->blocked} of them stop the order.';
$string['ordercheckcount_one'] = '1 line, {$a->blocked} of them stop the order.';
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
$string['orderdone'] = 'Course "{$a->course}" created. New accounts: {$a->created}, existing accounts enrolled: {$a->reused}.';
$string['orderdownload'] = 'Download credentials as Excel';
$string['orderduplicateemail'] = 'This address appears {$a} times in the list.';
$string['orderduplicateusername'] = 'User name "{$a}" appears more than once in this list.';
$string['orderduplicateusernamehint'] = 'For a shared address choose firstname.lastname as user names.';
$string['orderemailpattern'] = 'Address pattern';
$string['orderemailpattern_help'] = 'Optional. How the customer\'s addresses are built, for example '
    . 'firstname.lastname@customer.com. Understood are firstname and lastname (or vorname and nachname), f and l for the initials '
    . 'and * for anything; just the domain checks only the domain. Umlauts may be written either way. Addresses that do not fit '
    . 'are marked in the check but do not stop the order.';
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
$string['ordergotocourse'] = 'Go to the course';
$string['ordergotoparticipants'] = 'Go to the participant list';
$string['ordergroupentry'] = '{$a->name} ({$a->count})';
$string['ordergroupexisting'] = '{$a->name} ({$a->count}, already in the course)';
$string['ordergroupfailed'] = '{$a} could not be put into their group.';
$string['ordergroupheads'] = 'Lines without an address are group headings';
$string['ordergroupsdone'] = '{$a->members} people put into {$a->groups} groups.';
$string['ordergroupsdone_one'] = '1 person put into a group.';
$string['ordergroupsdone_onegroup'] = '{$a->members} people put into one group.';
$string['ordergroupsline'] = 'Groups: {$a}';
$string['orderhasheader'] = 'First line names the columns';
$string['orderheld'] = 'The credentials for {$a->count} people go out on {$a->date} at {$a->time}. Until then no mail is sent, and '
    . 'the course only opens for them on its start day.';
$string['orderheld_one'] = 'The credentials for 1 person go out on {$a->date} at {$a->time}. Until then no mail is sent, and the '
    . 'course only opens for them on its start day.';
$string['orderinvalidemail'] = '"{$a}" is not an address.';
$string['orderlist'] = 'Participant list';
$string['orderlist_help'] = 'Paste the list as the customer sent it, one person per line.';
$string['orderlistready'] = 'The passwords below are shown only now and are not stored. Download the list before leaving this '
    . 'page.';
$string['ordermailbody'] = 'Text';
$string['ordermailbody_default'] = '<p>Dear {firstname} {lastname},</p><p>{company} has registered you for the online instruction '
    . '<strong>{module}</strong> on {sitename}. It is available to you from now until <strong>{enddate}</strong>.</p><p>Your '
    . 'credentials:<br>User name: <strong>{username}</strong><br>Password: <strong>{password}</strong></p><p>This link takes you '
    . 'straight to the instruction: {link}<br>When you log in for the first time you will be asked to choose a password of your '
    . 'own.</p><p>Please complete all learning units and part tests by {enddate}. You do not have to do everything in one go, what '
    . 'you have completed is kept.</p><p>If you have any questions, contact us at {supportemail}.</p><p>Kind '
    . 'regards<br>{sitename}</p>';
$string['ordermailbody_desc'] = 'Placeholders: {firstname}, {lastname}, {username}, {password}, {course}, {module}, {company}, '
    . '{startdate}, {enddate}, {link}, {sitename}, {supportemail}. {link} leads straight into the course, {supportemail} is the '
    . 'support address from the site settings. A signature of your own belongs in this text as well.';
$string['ordermailed'] = '{$a} mails with credentials sent.';
$string['ordermailed_one'] = '1 mail with credentials sent.';
$string['ordermailfailed'] = 'Not every mail went out. The hourly run tries them again.';
$string['ordermailheading'] = 'Mail with the credentials';
$string['ordermailsubject'] = 'Subject';
$string['ordermailsubject_default'] = 'Your credentials for the online instruction {module}';
$string['ordermailsubject_desc'] = 'The same placeholders as in the text.';
$string['ordermailtaken'] = '{$a} other accounts already use this address.';
$string['ordermailtakenone'] = 'One other account already uses this address.';
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
$string['orderpatterncount'] = '{$a->flagged} of {$a->total} addresses do not fit the pattern {$a->pattern}.';
$string['orderpatterncount_one'] = '1 of {$a->total} addresses does not fit the pattern {$a->pattern}.';
$string['orderpatterndomain'] = 'The domain does not fit the address pattern, expected @{$a}.';
$string['orderpatternguess'] = 'Most addresses in the list follow {$a->pattern} ({$a->hits} of {$a->total}).';
$string['orderpatternmismatch'] = 'Does not fit the address pattern, expected something like {$a}.';
$string['orderpatternuse'] = 'Check against it';
$string['orderpeople'] = 'What was read';
$string['orderpeoplecount'] = '{$a->total} people, {$a->flawed} of them with notes.';
$string['orderpeoplecount_one'] = '1 person, {$a->flawed} of them with notes.';
$string['orderrefused'] = 'Nothing has been created yet. The red lines below still stop the order: correct them in the list or '
    . 'leave them out.';
$string['orderrelease'] = 'Open now and send';
$string['orderreleaseconfirm'] = 'The enrolments open at once and the credentials for {$a->count} people go out now instead of on '
    . '{$a->date} at {$a->time}. Continue?';
$string['orderreleaseconfirm_one'] = 'The enrolments open at once and the credentials for 1 person go out now instead of on '
    . '{$a->date} at {$a->time}. Continue?';
$string['orderreleased'] = 'Course opened, credentials for {$a} people sent.';
$string['orderreleased_one'] = 'Course opened, credentials for 1 person sent.';
$string['orderreuse'] = 'Use the existing account and only enrol it';
$string['orderreused'] = 'The existing account is used again and gets no new credentials.';
$string['orderreusehint'] = 'Only an organiser may keep an existing account.';
$string['orderrow'] = 'Line';
$string['orderruntime'] = 'Runtime';
$string['orders'] = 'Orders';
$string['orders_help'] = 'Every order made with "New order", also after its course has been deleted. Completions of a deleted '
    . 'course are the ones it had just before it was deleted.';
$string['orderscheme'] = 'User names';
$string['orderscheme_help'] = 'The address is the simpler choice, but it only works once per person. A shared mailbox therefore '
    . 'needs firstname.lastname.';
$string['orderschemeemail'] = 'Email address';
$string['orderschemename'] = 'firstname.lastname';
$string['orderscompleted'] = 'Completed';
$string['orderscreated'] = 'Created';
$string['orderscreator'] = 'Created by';
$string['ordersdeleted'] = '{$a->shortname} (deleted on {$a->date})';
$string['ordersdeletedundated'] = '{$a->shortname} (deleted)';
$string['ordersdelivery'] = 'Credentials';
$string['ordersdeliveryboth'] = 'Mail and list';
$string['ordersdeliverylist'] = 'List';
$string['ordersdeliverymail'] = 'Mail';
$string['ordersendtime'] = 'Send time';
$string['ordersendtime_desc'] = 'When a course starts later, its credentials go out at this time on the start day, more precisely '
    . 'with the first hourly run after it. The course itself opens at midnight.';
$string['ordersfile'] = 'Orders';
$string['ordershortname'] = 'Course short name';
$string['orderskip'] = 'Leave out';
$string['orderskipped'] = 'Left out, nothing is created for this line.';
$string['orderskippedcount'] = '{$a} lines are left out.';
$string['orderskippedcount_one'] = '1 line is left out.';
$string['ordersnomatch'] = 'No order matches the search.';
$string['ordersnone'] = 'No orders yet.';
$string['ordersorganisers'] = 'Organisers';
$string['ordersparticipants'] = 'Participants';
$string['ordersremove'] = 'Remove entry';
$string['ordersremoveconfirm'] = 'Remove the order {$a->shortname} of {$a->company} (order number {$a->abnr}) from the list for '
    . 'good? Accounts and courses are not affected.';
$string['ordersremovecourse'] = 'The course of this order still exists. Only orders whose course has been deleted can be removed '
    . 'from the list.';
$string['ordersremoved'] = 'Order {$a} removed from the list.';
$string['orderssearch'] = 'Company, order number or short name';
$string['ordersstarts'] = 'starts on {$a}';
$string['orderstartdate'] = 'Course start';
$string['orderstartdate_help'] = 'A start in the future holds back the enrolment, and the credentials with it.';
$string['orderstartpassword'] = 'Start password';
$string['orderupdate'] = 'Update';
$string['orderusername'] = 'User name';
$string['orderwithoutcourse'] = 'The course of this order no longer exists.';
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
$string['statusupcoming'] = 'Starts later';
$string['tasknotifycompleted'] = 'Notify about fully completed courses';
$string['tasksendcredentials'] = 'Send the credentials of orders that are due';
$string['totalparticipants'] = 'Total Participants';
$string['withoutparticipants'] = 'Show courses without participants';
