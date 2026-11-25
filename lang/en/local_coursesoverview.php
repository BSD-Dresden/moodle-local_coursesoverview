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
$string['actiondone'] = 'Done';
$string['actionrelease'] = 'Release again';
$string['applyfilters'] = 'Filter';
$string['backtooverview'] = 'Back to courses overview';
$string['certificatebold'] = 'Without placeholders: name and date in bold';
$string['certificatebutton'] = 'Certificates of attendance ({$a})';
$string['certificatedefault'] = 'General template';
$string['certificatedefault_desc'] = 'For modules without a template of their own and for courses without an order. ' .
    'Leave empty for no certificates there.';
$string['certificatefailed'] = 'The certificates could not be made. Is the template a PDF?';
$string['certificatefile'] = 'Certificate {$a}';
$string['certificatefontsize'] = 'Without placeholders: font size in points';
$string['certificatemodule'] = 'Template for {$a}';
$string['certificatemodule_desc'] = 'Instead of the general template. Leave empty for the general one.';
$string['certificatenone'] = 'Nobody has completed this course yet.';
$string['certificatenotemplate'] = 'There is no template for certificates of attendance for this course.';
$string['certificatepos'] = 'Without placeholders: position of name and date';
$string['certificatepos_desc'] = 'Only for templates without placeholders. Four numbers in millimetres from the top ' .
    'left corner: name across and down, then date across and down. Measured to the baseline, the bottom of the letters ' .
    'without descenders. Example: 18.5 84.8 30.8 142.4';
$string['certificatepos_module_desc'] = 'As for the general template. Leave empty to use its position.';
$string['certificatepreview'] = 'Open preview with grid';
$string['certificatepreviewfile'] = 'Certificate preview';
$string['certificates'] = 'Certificates of attendance';
$string['certificates_desc'] = 'PDF templates for the certificates of attendance, one file per person with their own ' .
    'completion date, all together as a ZIP. To make a template, write {name} where the name goes and {date} where the ' .
    'date goes in Word, in the font, size and alignment the text should have and in white, so that the placeholders do ' .
    'not get in the way in Word; the finished certificates do not contain them at all. Then save as PDF and upload it here. More placeholders: {firstname}, {lastname}, {company}, {course}, ' .
    '{module}, {title}. The text starts where the placeholder starts; {name:center} puts it on its middle, ' .
    '{name:right} at its end. Moodle knows no form of address, so the text should do without "Mr" and "Ms". Once ' .
    'saved, the preview with a grid shows where everything lands.';
$string['certificatesample'] = 'Sample text';
$string['certificatesamplefirstname'] = 'Jane';
$string['certificatesamplelastname'] = 'Doe';
$string['certificatesamplename'] = 'Jane Doe';
$string['certificateshort'] = 'Certificate';
$string['certificatesingle'] = 'Certificate of attendance as PDF';
$string['certificatetemplate'] = 'Template (PDF)';
$string['certificatezip'] = 'Certificates {$a}';
$string['colemail'] = 'Email address';
$string['colfirstname'] = 'First name';
$string['colfullname'] = 'First name and surname';
$string['colignore'] = 'Not needed';
$string['collastfirst'] = 'Surname, first name';
$string['collastname'] = 'Surname';
$string['colnamemail'] = 'Name and address in one cell';
$string['colrole'] = 'Role';
$string['colstartdate'] = 'Start date';
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
$string['coursedone'] = 'Course "{$a}" is done and hidden from its participants.';
$string['coursereleased'] = 'Course "{$a}" is released again.';
$string['coursesoverview:createorder'] = 'Create an order together with its course';
$string['coursesoverview:view'] = 'View the courses overview';
$string['deletechecked'] = 'I have checked the list';
$string['deleteconfirm'] = 'Permanently delete the course and the accounts listed';
$string['deletedone'] = 'Course "{$a->course}" deleted, {$a->users} accounts removed.';
$string['deletedone_one'] = 'Course "{$a->course}" deleted, 1 account removed.';
$string['deleteheading'] = 'Delete course: {$a}';
$string['deleteirreversible'] = 'Deleting cannot be undone. Check both lists.';
$string['deletenotdone'] = 'Only courses marked as done can be deleted here. Mark the course as done first.';
$string['deletenousers'] = 'No account will be deleted, only the course.';
$string['deleteunchecked'] = 'Please confirm that you have checked the list.';
$string['deleteusers'] = 'These {$a} accounts will be permanently deleted';
$string['deleteusers_one'] = 'This account will be permanently deleted';
$string['delimiterauto'] = 'Work it out';
$string['delimitercomma'] = 'Comma';
$string['delimitersemicolon'] = 'Semicolon';
$string['delimiterspace'] = 'Blanks';
$string['delimitertab'] = 'Tab';
$string['donedate'] = 'Done on';
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
$string['noticebody'] = 'Text';
$string['noticebody_default'] = '<p>Dear {firstname} {lastname},</p><p>the following people have just received their '
    . 'credentials for <strong>{module}</strong> by mail:</p>{participants}<p>You can follow their progress in the course '
    . 'at {link}.</p><p>Kind regards<br>{sitename}</p>';
$string['noticebody_desc'] = 'The placeholders of the credentials mail, plus {participants} for the list of people with '
    . 'name and address and {count} for how many they are. {firstname} and {lastname} are the name of the organiser here. '
    . '{startdate} is the start of the course.';
$string['noticeheading'] = 'Notice to organisers';
$string['noticeheading_desc'] = 'Text of the mail to the organisers. Whether it goes out is ticked when an order is '
    . 'created and with every late report, in the box "Notify organisers". It goes out once the credentials are sent, one '
    . 'mail per organiser and sending. Organisers in groups only hear of their groups, organisers in no group of '
    . 'everybody.';
$string['noticesubject'] = 'Subject';
$string['noticesubject_default'] = 'Credentials sent: {module}';
$string['noticesubject_desc'] = 'The same placeholders as in the text.';
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
$string['orderboxschedule'] = 'All dates';
$string['orderboxscheduleentry'] = '{$a->date}: {$a->count}';
$string['orderboxwaiting'] = 'Credentials for {$a->count} people are waiting for {$a->date}, {$a->time}.';
$string['orderboxwaiting_one'] = 'Credentials for 1 person are waiting for {$a->date}, {$a->time}.';
$string['orderboxwaitingspread'] = 'Credentials for {$a->count} people are waiting, spread over {$a->days} days: next '
    . '{$a->firstcount} on {$a->first}, last on {$a->last}, each at {$a->time}.';
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
$string['orderendfromstart'] = 'Start + {$a} months';
$string['orderendfromstart_one'] = 'Start + 1 month';
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
$string['orderheld'] = 'The credentials for {$a->count} people go out on {$a->date} at {$a->time}. Until then no mail is '
    . 'sent, and the course only opens for them on that day.';
$string['orderheld_one'] = 'The credentials for 1 person go out on {$a->date} at {$a->time}. Until then no mail is sent, '
    . 'and the course only opens for them on that day.';
$string['orderheldspread'] = 'The credentials for {$a->count} people go out on each person\'s start date at {$a->time}, '
    . 'spread over {$a->days} days from {$a->first} to {$a->last}. Until then no mail is sent, and the course only opens '
    . 'for each person on their start date. All dates are listed in the order box on the completion status.';
$string['orderinvalidemail'] = '"{$a}" is not an address.';
$string['orderinvalidstart'] = 'Start date "{$a}" not readable, expected something like 02.12.2026.';
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
$string['ordermailbody_desc'] = 'Placeholders: {firstname}, {lastname}, {username}, {password}, {course}, {module}, {title}, '
    . '{company}, {startdate}, {enddate}, {link}, {sitename}, {supportemail}. {title} is the title of the module from the module '
    . 'list, or its name when it has none. {link} leads straight into the course, {supportemail} is the support address from the '
    . 'site settings. A signature of your own belongs in this text as well.';
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
$string['ordermodules_desc'] = 'One module per line, fields separated by a vertical bar: name, short name of the template course, '
    . 'category id, prefix of the generated course short name, and optionally a title for the mails. An empty category id puts the '
    . 'new course where its template sits. The name appears in the form, the list of orders and through {module} in the course '
    . 'name, the title only in the mails through {title}.<br />Example: <code>Grundmodul | OJU_Vorlage_GM | 12 | OJU_GM | Sicheres '
    . 'Arbeiten an elektrischen Anlagen</code>';
$string['ordermonths'] = 'Runtime in months';
$string['ordermonths_desc'] = 'Prefills the course end when an order is created, and can be changed per order.';
$string['ordernolines'] = 'Nothing could be read from the pasted list.';
$string['ordernomanualenrol'] = 'The new course has no manual enrolment, so nobody could be enrolled.';
$string['ordernomodules'] = 'No module has been configured yet. An order needs at least one.';
$string['ordernopeople'] = 'The list yielded nobody. Check the separator and the assignment.';
$string['ordernotcreated'] = 'Account "{$a}" was not created.';
$string['ordernotes'] = 'Notes';
$string['ordernotify'] = 'Notify organisers';
$string['ordernotify_help'] = 'The organisers of the course get a mail with the names once the credentials of this order '
    . 'or report have been mailed, without passwords. Only when credentials go to the participants. The text is under '
    . 'Settings, Notice to organisers.';
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
$string['orderreleaseconfirmspread'] = 'The enrolments open at once, and the credentials for {$a->count} people go out now '
    . 'instead of on their start dates from {$a->first} to {$a->last}. Continue?';
$string['orderreleased'] = 'Course opened, credentials for {$a} people sent.';
$string['orderreleased_one'] = 'Course opened, credentials for 1 person sent.';
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
$string['ordershortname'] = 'Course short name';
$string['orderskip'] = 'Leave out';
$string['orderskipped'] = 'Left out, nothing is created for this line.';
$string['orderskippedcount'] = '{$a} lines are left out.';
$string['orderskippedcount_one'] = '1 line is left out.';
$string['ordersorganisers'] = 'Organisers';
$string['ordersremove'] = 'Remove entry';
$string['ordersremoveconfirm'] = 'Remove the order {$a->shortname} of {$a->company} (order number {$a->abnr}) from the list for '
    . 'good? Accounts and courses are not affected.';
$string['ordersremovecourse'] = 'The course of this order still exists. Only orders whose course has been deleted can be removed '
    . 'from the list.';
$string['ordersremoved'] = 'Order {$a} removed from the list.';
$string['ordersstarts'] = 'starts on {$a}';
$string['orderstart'] = 'Start';
$string['orderstartafterend'] = 'Start date {$a} is after the end of the course.';
$string['orderstartdate'] = 'Course start';
$string['orderstartdate_help'] = 'A start in the future holds back the enrolment, and the credentials with it.';
$string['orderstartmoved'] = 'Start date {$a->given} is earlier, starts on {$a->start}.';
$string['orderstartpassword'] = 'Start password';
$string['orderupdate'] = 'Update';
$string['orderusername'] = 'User name';
$string['orderwithoutcourse'] = 'The course of this order no longer exists.';
$string['organiser'] = 'Organiser';
$string['organisers'] = 'Organisers';
$string['origin'] = 'Origin';
$string['originnone'] = 'Without order';
$string['originorder'] = 'With order';
$string['overview'] = 'Courses overview';
$string['pluginname'] = 'Training management';
$string['progressheader'] = 'Sections completed / total';
$string['remind_help'] = 'Only participants who have not completed the course are reminded. Automatic reminders go out at the send '
    . 'time of the credentials. Somebody who had a reminder in the last two days, also one sent by hand, gets no automatic one, so '
    . 'two reminders on the same day make one mail.';
$string['reminderafter'] = 'Days after enrolment';
$string['reminderafter_desc'] = 'Numbers separated by commas, for example 14, 28. Counted from each person\'s own enrolment, which '
    . 'suits courses that take people in over a long time. Empty for none.';
$string['reminderafter_help'] = 'Counted from each person\'s own enrolment. Numbers separated by commas. Days typed here count '
    . 'instead of the settings, an empty field without the tick means none.';
$string['reminderbefore'] = 'Days before the course ends';
$string['reminderbefore_desc'] = 'Numbers separated by commas, for example 14, 3. Counted from the course end as it is at the '
    . 'time, so an extended course reminds again. Empty for none.';
$string['reminderbefore_help'] = 'Counted from the current course end, after an extension it starts again. Numbers separated by '
    . 'commas. Days typed here count instead of the settings, an empty field without the tick means none.';
$string['reminderbody'] = 'Text';
$string['reminderbody_default'] = '<p>Dear {firstname} {lastname},</p><p>you have not completed the online instruction '
    . '<strong>{module}</strong> on {sitename} yet. It is available to you until <strong>{enddate}</strong>.</p><p>This link takes '
    . 'you straight to the instruction: {link}<br>Your user name: <strong>{username}</strong></p><p>If you have forgotten your '
    . 'password, you can reset it on the login page. If you have any questions, contact us at {supportemail}.</p><p>Kind '
    . 'regards<br>{sitename}</p>';
$string['reminderbody_desc'] = 'Placeholders: {firstname}, {lastname}, {username}, {course}, {module}, {title}, {company}, '
    . '{startdate}, {enddate}, {daysleft}, {link}, {sitename}, {supportemail}. {title} is the title of the module from the module '
    . 'list, or its name when it has none. {daysleft} is the number of days until the course ends.';
$string['reminderboxafter'] = '{$a} days after enrolment';
$string['reminderboxbefore'] = '{$a} days before the end';
$string['reminderboxinfo'] = 'Reminders: {$a}.';
$string['reminderboxnone'] = 'No automatic reminders.';
$string['remindercompleted'] = 'Completed';
$string['reminderconfirm'] = '{$a} people get a reminder now.';
$string['reminderconfirm_one'] = '1 person gets a reminder now.';
$string['reminderdefault'] = 'As in the settings ({$a})';
$string['reminderfailed'] = 'Not every mail went out.';
$string['reminderhad'] = 'Reminded';
$string['reminderheading'] = 'Reminders';
$string['reminderheading_desc'] = 'Reminders for participants who have not completed their course, only in courses of orders. Each '
    . 'course can have its own days on its reminder page. Orders made before reminders existed have none until they are switched '
    . 'on there.';
$string['reminderinvaliddays'] = '"{$a}" is not a list of days. Use numbers from 1 to 999, separated by commas.';
$string['reminderjoined'] = 'Enrolled';
$string['reminderkindafter'] = '{$a->date}, {$a->days} days after enrolment';
$string['reminderkindafter_one'] = '{$a->date}, 1 day after enrolment';
$string['reminderkindbefore'] = '{$a->date}, {$a->days} days before the end';
$string['reminderkindbefore_one'] = '{$a->date}, 1 day before the end';
$string['reminderkindmanual'] = '{$a->date}, by hand';
$string['remindernext'] = 'Next reminder';
$string['remindernextat'] = '{$a->date}, {$a->time}';
$string['remindernobody'] = 'Nobody who is still open was selected.';
$string['remindernoend'] = 'none';
$string['remindernone'] = 'none';
$string['remindernopeople'] = 'Nobody to remind: the course has no participants or does not track completion.';
$string['reminderopen'] = 'open';
$string['reminderpeople'] = 'Participants';
$string['reminderpreview'] = 'This is the mail for {$a}:';
$string['reminders'] = 'Reminders';
$string['reminderschedule'] = 'Schedule';
$string['reminderscheduled'] = 'Reminders saved.';
$string['remindersend'] = 'Send reminder';
$string['remindersendnow'] = 'Send now';
$string['remindersent'] = '{$a} reminders sent.';
$string['remindersent_one'] = '1 reminder sent.';
$string['remindersubject'] = 'Subject';
$string['remindersubject_default'] = 'Reminder: online instruction {module}';
$string['remindersubject_desc'] = 'The same placeholders as in the text.';
$string['remindheading'] = 'Reminders: {$a}';
$string['roleboth'] = 'Organiser and participant';
$string['roleorganiser'] = 'Organiser';
$string['roleparticipant'] = 'Participant';
$string['settings'] = 'Settings';
$string['settingsorders'] = 'Orders and modules';
$string['statusactive'] = 'Ongoing';
$string['statuscompleted'] = 'Completed';
$string['statusdeleted'] = 'Deleted';
$string['statusdone'] = 'Done';
$string['statusdoneon'] = 'Done on {$a}';
$string['statusendingsoon'] = 'Ending soon, not completed';
$string['statushidden'] = 'Hidden';
$string['statusinprogress'] = 'Started, not completed';
$string['statusnotstarted'] = 'Not started';
$string['statusoverdue'] = 'Ended, not completed';
$string['statuspast'] = 'Finished';
$string['statustodo'] = 'Needs attention';
$string['statustosettle'] = 'Completed, not yet done';
$string['statusupcoming'] = 'Starts later';
$string['tasknotifycompleted'] = 'Notify about fully completed courses';
$string['tasksendcredentials'] = 'Send the credentials of orders that are due';
$string['tasksendreminders'] = 'Send due reminders of orders';
$string['totalparticipants'] = 'Total Participants';
$string['withoutparticipants'] = 'Show courses without participants';
