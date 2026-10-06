import { visiblePublicMenu } from './publicMenu.js';
import { visibleAdminEntries } from './adminMenuModel.js';

export const PAGE_SIZE = 8;
export const RESULT_VALUES = ['works', 'fails', 'skipped'];

const SAFE = 'Do not delete a real record, pay real money, or empty the database.';

function check(id, text, steps, extra = {}) {
  return { id, text, steps, ...extra };
}

function slugId(entry) {
  const raw = `${entry.group || ''}-${entry.section || ''}-${entry.path || ''}`;
  return `nav-${raw
    .toLowerCase()
    .replace(/[^a-z0-9]+/g, '-')
    .replace(/^-|-$/g, '')}`;
}

const PUBLIC_CHECKS = [
  check('home-open', 'Open the homepage. You should see the Beyond Enterprise logo, the public menu, EN and FR, and Login.', [
    'Leave this test open. Open a new browser tab.',
    'Go to the site homepage, the address ending in /.',
    'At the top, confirm the logo, the links Home, Training, Events, Register Now, Apply Now, About Us, Shareholders and Contact Us, plus Scan QR.',
    'Confirm EN and FR are on the right, and a Login button is there. There is no Donate button.',
  ]),
  check('home-lang', 'Switch the homepage language with EN and FR.', [
    'On the homepage, click FR.',
    'Menu words and page text should change to French.',
    'Click EN. The page should return to English.',
  ]),
  check('public-training', 'Open Training. You should see the training courses, or a clear message that none are listed.', [
    'On the homepage, click Training. On a phone it is inside the menu.',
    'The address should be /trainings.',
    'You should see course cards or a clear empty message, not an error page.',
  ]),
  check('public-events', 'Open Events. The heading should read Events & Highlights.', [
    'Click Events in the menu.',
    'The heading Events & Highlights should show, with the line about workshops and technology showcases.',
    'If an event card is listed, open it. The event title should show. If none are listed, the empty message should be clear.',
  ]),
  check('public-register', 'Open Register Now. You should see course choices. Do not submit a real registration unless the name starts with TEST.', [
    'Click Register Now.',
    'The blue heading Register Now should show, with text about joining Beyond Enterprise and selecting courses.',
    'The course list or form should be on the page. Stop before the final submit, or use a name that starts with TEST.',
  ]),
  check('public-apply', 'Open Apply Now. The heading should read Build Your Future With Us.', [
    'Click Apply Now. It is highlighted in gold.',
    'You should see Build Your Future With Us, and either job cards or a clear message that there are no open jobs.',
    'If a job is listed, open Apply. The application form should show name and contact fields. Do not send a real application unless the name starts with TEST.',
  ]),
  check('public-about', 'Open About Us. You should see the vision line and the team.', [
    'Click About Us.',
    'The blue banner should say Bridging Technology & Innovation.',
    'Further down, Mission and Vision should be readable, and a team or members section should show. It should not be an error page.',
  ]),
  check('public-shareholders', 'Open Shareholders. Read the page. Do not pay and do not buy shares.', [
    'Click Shareholders.',
    'The shareholders page should explain ownership and the share process.',
    'If a buy or pay button is visible, do not click through to a real payment.',
  ]),
  check('public-contact', 'Open Contact Us. You should see a message form and the company phone and email.', [
    'Click Contact Us, or open /contact.',
    'The form should have name, email, subject, and message.',
    'The company phone and email should be visible on the page. You may send a message whose subject starts with TEST.',
  ]),
  check('public-scan', 'Open Scan QR. The scanner page should load.', [
    'Click Scan QR in the menu.',
    'The address should be /qr-scanner.',
    'You should see a scanner or a camera prompt, not an error page. You can deny the camera and still mark whether the page itself opened.',
  ]),
  check('public-mobile', 'Repeat the homepage on a phone, or in a narrow window.', [
    'Make the browser window narrow, or open the homepage on a phone.',
    'Tap the menu button, the three lines at the top.',
    'Home, Training, Events, Register Now, Apply Now, About Us, Shareholders, Contact Us, and Scan QR should be easy to tap. The page should not slide sideways.',
  ]),
];

const LOGIN_CHECKS = [
  check('login-ok', 'Sign in with the test username and password you were given on WhatsApp.', [
    'On the homepage, click Login.',
    'Type the username and the password from the WhatsApp message. If WhatsApp did not deliver them, use the username and password shown once on the test screen.',
    'After the password, the site sends a WhatsApp code. Enter that code.',
    'You should reach the admin area. A blue menu appears on the left. The title reads Beyond Enterprise.',
  ]),
  check('login-bad', 'Try a wrong password. The site should refuse it.', [
    'Open Login again, or sign out first.',
    'Type the right username and a wrong password.',
    'The site should refuse it. You should not see the blue admin menu.',
  ]),
  check('logout', 'Sign out.', [
    'Sign in again with the correct password and the WhatsApp code.',
    'At the bottom of the blue menu, click Sign Out.',
    'You should leave the admin area, and the blue menu should be gone.',
  ]),
];

const ADMIN_DETAIL = {
  '/admin/dashboard': {
    text: 'Open Dashboard. You should see the admin home with shortcuts into the system.',
    steps: [
      'Sign in. In the blue menu, click Dashboard.',
      'The address should be /admin/dashboard.',
      'You should see summary cards or shortcuts, including a way toward General Settings. The page should not be blank or an error.',
    ],
  },
  '/admin/tasks/dashboard': {
    text: 'Open Task Dashboard. You should see task counts or an empty task summary.',
    steps: [
      'In the blue menu, click Task Management. The top bar should list Task Dashboard, Create Task, All Tasks, and the other task links.',
      'Click Task Dashboard if it is not already open.',
      'You should see counts, charts, or a clear empty state. Do not delete a task.',
    ],
  },
  '/admin/tasks/create': {
    text: 'Open Create Task. The form should ask for a title and a deadline. Do not assign a real person unless the title starts with TEST.',
    steps: [
      'Under Task Management, click Create Task.',
      'The form should show a title, description, dates, and a way to choose a person.',
      'Stop before saving, or save a task whose title starts with TEST. Do not delete someone else’s task.',
    ],
  },
  '/admin/tasks': {
    text: 'Open All Tasks. The list of tasks should show, or a clear empty message.',
    steps: [
      'Under Task Management, click All Tasks.',
      'The address should be /admin/tasks.',
      'Rows should show a title and a status, or the page should say there are no tasks. Do not delete a real task.',
    ],
  },
  '/admin/tasks/scheduled': {
    text: 'Open Scheduled tasks. You should see scheduled items, or a clear empty message.',
    steps: [
      'Under Task Management, click Scheduled.',
      'The page should load at /admin/tasks/scheduled.',
      'Scheduled tasks should be listed, or the empty message should be clear.',
    ],
  },
  '/admin/tasks/reminders': {
    text: 'Open Reminders. You should see reminder controls, not an error.',
    steps: [
      'Under Task Management, click Reminders.',
      'The page should explain reminders or list them.',
      'Do not send a reminder to a real customer unless the task title starts with TEST.',
    ],
  },
  '/admin/tasks/my-tasks': {
    text: 'Open My Tasks inside the admin task menu.',
    steps: [
      'Under Task Management, click My Tasks.',
      'The address should be /admin/tasks/my-tasks.',
      'You should see tasks assigned to you, or a clear empty message.',
    ],
  },
  '/admin/tasks/pending-acceptances': {
    text: 'Open Pending Acceptances. You should see people who have not accepted a task, or a clear empty message.',
    steps: [
      'Under Task Management, click Pending Acceptances.',
      'The list should show a person and a task, or say there is nothing pending.',
      'Do not reject a real assignment.',
    ],
  },
  '/admin/tasks/settings': {
    text: 'Open Task Settings. These are task options, not the system Settings menu.',
    steps: [
      'Under Task Management, click Task Settings.',
      'The address should be /admin/tasks/settings.',
      'You should see task options such as categories or defaults. Do not change a setting you cannot put back. This is not General Settings.',
    ],
  },
  '/admin/recruitment-dashboard': {
    text: 'Open Recruitment Dashboard. You should see job and application numbers, or an empty summary.',
    steps: [
      'In the blue menu, click Job Board. The top bar should show Recruitment Dashboard, Manage Jobs, and the application lists.',
      'Open Recruitment Dashboard.',
      'Counts or cards should show. The page should not be an error.',
    ],
  },
  '/admin/jobs': {
    text: 'Open Manage Jobs. You should see the job list and a way to add a job.',
    steps: [
      'Under Job Board, click Manage Jobs.',
      'Existing jobs should be listed, or the list should be clearly empty.',
      'You may open Add. Do not publish a real job unless the title starts with TEST.',
    ],
  },
  '/admin/applications': {
    text: 'Open All Applications. You should see applications, or a clear empty message.',
    steps: [
      'Under Job Board, click All Applications.',
      'Each row should show a person and a job, or the page should say there are none.',
      'Do not reject or delete a real application.',
    ],
  },
  '/admin/applications/shortlisted': {
    text: 'Open Shortlisted. Only shortlisted applications should show, or a clear empty message.',
    steps: [
      'Under Job Board, click Shortlisted.',
      'The address should be /admin/applications/shortlisted.',
      'The list should match the heading. Do not change a real applicant’s status.',
    ],
  },
  '/admin/applications/rejected': {
    text: 'Open Rejected. Only rejected applications should show, or a clear empty message.',
    steps: [
      'Under Job Board, click Rejected.',
      'The address should be /admin/applications/rejected.',
      'Do not restore or delete a real application.',
    ],
  },
  '/admin/events': {
    text: 'Open Event Management. The heading should be about events, meals, or the event list.',
    steps: [
      'In the blue menu, click Event Management.',
      'The address should be /admin/events.',
      'You should see events or a clear empty state, and links such as meals or create. Do not delete a real event.',
    ],
  },
  '/admin/invitations': {
    text: 'Open Digital Invitations. You should see invitations, or a clear empty message.',
    steps: [
      'In the blue menu, click Digital Invitations.',
      'The address should be /admin/invitations.',
      'The page should list invitations or say there are none. Do not send an invitation to a real guest unless the name starts with TEST.',
    ],
  },
  '/admin/events/templates': {
    text: 'Open Event Templates & Config. You should see template or WhatsApp template tools.',
    steps: [
      'In the blue menu, click Event Templates & Config.',
      'The address should be /admin/events/templates.',
      'You should see design templates, WhatsApp templates, or webhook links. Do not delete a template that is already in use.',
    ],
  },
  '/admin/announcements/compose': {
    text: 'Open Announcements Compose. The form should let you write a message. Do not send it to real people.',
    steps: [
      'In the blue menu, click Announcements. The top bar should show Compose, All Announcements, Scheduled, Templates, Categories, and Settings.',
      'Open Compose.',
      'You should see a message box and recipients. Stop before sending. Do not send a bulk WhatsApp message.',
    ],
  },
  '/admin/announcements/list': {
    text: 'Open All Announcements. The list should show past announcements, or a clear empty message.',
    steps: [
      'Under Announcements, click All Announcements.',
      'The address should be /admin/announcements/list.',
      'Rows should show a title or message, or the empty message should be clear. Do not delete a real announcement.',
    ],
  },
  '/admin/announcements/scheduled': {
    text: 'Open Scheduled announcements.',
    steps: [
      'Under Announcements, click Scheduled.',
      'The address should be /admin/announcements/scheduled.',
      'Scheduled messages should be listed, or the page should say there are none.',
    ],
  },
  '/admin/announcements/templates': {
    text: 'Open announcement Templates.',
    steps: [
      'Under Announcements, click Templates.',
      'Saved templates should show, or a clear empty message, plus a way to add one.',
      'Do not delete a template that staff already use.',
    ],
  },
  '/admin/announcements/categories': {
    text: 'Open announcement Categories.',
    steps: [
      'Under Announcements, click Categories.',
      'Category names should be listed, or the page should say there are none.',
      'Do not rename a category that is already in use unless you can put the old name back.',
    ],
  },
  '/admin/announcements/settings': {
    text: 'Open announcement Settings. This is the announcements module, not General Settings.',
    steps: [
      'Under Announcements, click Settings.',
      'The address should be /admin/announcements/settings.',
      'You should see announcement options such as sender or SMS toggles for messages. Do not turn off a live sender. This is not the system Settings menu.',
    ],
  },
  '/admin/timesheet/create-activity': {
    text: 'Open Create Activity. The form should ask what the activity is called.',
    steps: [
      'In the blue menu, click TimeSheets (Employee). The top bar should show Create Activity, Fill Time Sheet, and Working Week.',
      'Open Create Activity.',
      'A name field should be on the form. Do not save an activity unless the name starts with TEST.',
    ],
  },
  '/admin/timesheet/fill-timesheet': {
    text: 'Open Fill Time Sheet. You should see days or hours to fill.',
    steps: [
      'Under TimeSheets (Employee), click Fill Time Sheet.',
      'The address should be /admin/timesheet/fill-timesheet.',
      'You should see a week or a list of activities. Do not change another person’s hours.',
    ],
  },
  '/admin/timesheet/working-week': {
    text: 'Open Working Week. You should see the working days.',
    steps: [
      'Under TimeSheets (Employee), click Working Week.',
      'Days of the week should be listed.',
      'Do not change the company working week.',
    ],
  },
  '/admin/timesheet-report': {
    text: 'Open TimeSheet Report. A report or an empty report should show.',
    steps: [
      'In the blue menu, click TimeSheet Admin, then TimeSheet Report.',
      'The address should be /admin/timesheet-report.',
      'You should see hours, filters, or a clear empty report. Do not delete rows.',
    ],
  },
  '/admin/overtime-report': {
    text: 'Open Overtime Report.',
    steps: [
      'Under TimeSheet Admin, click Overtime Report.',
      'The address should be /admin/overtime-report.',
      'Overtime figures or a clear empty message should show.',
    ],
  },
  '/admin/manage-timesheets': {
    text: 'Open Manage All timesheets.',
    steps: [
      'Under TimeSheet Admin, click Manage All.',
      'The address should be /admin/manage-timesheets.',
      'Staff timesheets should be listed, or the page should say there are none. Do not delete a real timesheet.',
    ],
  },
  '/admin/timesheet-categories': {
    text: 'Open timesheet Categories.',
    steps: [
      'Under TimeSheet Admin, click Categories.',
      'Category names should show, or a clear empty message.',
      'Do not delete a category that is already used.',
    ],
  },
  '/admin/payments': {
    text: 'Open Payments. You should see payment records. Do not take or refund real money.',
    steps: [
      'In the blue menu, click Payments.',
      'The address should be /admin/payments.',
      'A list of payments or a clear empty message should show. Do not record a real payment and do not mark a real invoice paid.',
    ],
  },
  '/admin/courses': {
    text: 'Open Course List. Courses should be listed, or the page should say there are none.',
    steps: [
      'In the blue menu, click Courses. The top bar should show Course List, Add Course, Registrations, Invoices, Certificates, Student Progress, and Feedback.',
      'Open Course List.',
      'Each course should show a name, or the empty message should be clear. Do not delete a real course.',
    ],
  },
  '/admin/courses/add': {
    text: 'Open Add Course. The form should ask for a course name. Do not publish a real course unless the name starts with TEST.',
    steps: [
      'Under Courses, click Add Course.',
      'The address should be /admin/courses/add.',
      'Name and description fields should be visible. Stop before saving, or use a name that starts with TEST.',
    ],
  },
  '/admin/registrations': {
    text: 'Open Registrations. Student or course registrations should show, or a clear empty message.',
    steps: [
      'Under Courses, click Registrations.',
      'The address should be /admin/registrations.',
      'Do not delete a real registration.',
    ],
  },
  '/admin/invoices': {
    text: 'Open Invoices. Invoice rows should show, or a clear empty message. Do not take a payment.',
    steps: [
      'Under Courses, click Invoices.',
      'The address should be /admin/invoices.',
      'An invoice number or a name should be visible if any exist. Do not mark a real invoice paid.',
    ],
  },
  '/admin/certificates': {
    text: 'Open Certificates.',
    steps: [
      'Under Courses, click Certificates.',
      'The address should be /admin/certificates.',
      'Certificates or a clear empty message should show. Do not issue a certificate to a real student unless the name starts with TEST.',
    ],
  },
  '/admin/progress': {
    text: 'Open Student Progress.',
    steps: [
      'Under Courses, click Student Progress.',
      'The address should be /admin/progress.',
      'Progress rows or a clear empty message should show.',
    ],
  },
  '/admin/feedback': {
    text: 'Open Feedback.',
    steps: [
      'Under Courses, click Feedback.',
      'The address should be /admin/feedback.',
      'Feedback entries or a clear empty message should show. Do not delete a real comment.',
    ],
  },
  '/admin/hr/staff': {
    text: 'Open Staff Management. Staff names should show, or a clear empty message.',
    steps: [
      'In the blue menu, click Human Resources. The top bar should include Staff Management, payroll, allowances, and payslips.',
      'Open Staff Management.',
      'The address should be /admin/hr/staff. Do not delete a real staff record. A new record must start with TEST if you save one.',
    ],
  },
  '/admin/hr/categories': {
    text: 'Open Staff Categories.',
    steps: [
      'Under Human Resources, click Staff Categories.',
      'The address should be /admin/hr/categories.',
      'Categories should be listed, or the empty message should be clear. Do not delete a category that staff already use.',
    ],
  },
  '/admin/hr/jobs': {
    text: 'Open Job / Event Payroll. Do not pay real wages.',
    steps: [
      'Under Human Resources, click Job / Event Payroll.',
      'The address should be /admin/hr/jobs.',
      'Jobs or payroll rows should show, or a clear empty message. Do not approve a real payment.',
    ],
  },
  '/admin/hr/monthly-payroll': {
    text: 'Open Monthly Payroll. Do not run a real payroll.',
    steps: [
      'Under Human Resources, click Monthly Payroll.',
      'The address should be /admin/hr/monthly-payroll.',
      'A month and staff lines should show, or a clear empty state. Do not finalise a real payroll.',
    ],
  },
  '/admin/hr/allowances': {
    text: 'Open Allowances.',
    steps: [
      'Under Human Resources, click Allowances.',
      'The address should be /admin/hr/allowances.',
      'Allowance types or rows should show. Do not change a real staff member’s pay.',
    ],
  },
  '/admin/hr/deductions': {
    text: 'Open Deductions.',
    steps: [
      'Under Human Resources, click Deductions.',
      'The address should be /admin/hr/deductions.',
      'Deduction types or rows should show. Do not change a real staff member’s pay.',
    ],
  },
  '/admin/hr/advances': {
    text: 'Open Advance Payments. Do not record a real cash advance.',
    steps: [
      'Under Human Resources, click Advance Payments.',
      'The address should be /admin/hr/advances.',
      'Advances should be listed, or the empty message should be clear. Do not pay real money.',
    ],
  },
  '/admin/hr/payslips': {
    text: 'Open Payslips. A payslip list should show, or a clear empty message.',
    steps: [
      'Under Human Resources, click Payslips.',
      'The address should be /admin/hr/payslips.',
      'You may open a payslip to read it. Do not send it on WhatsApp to a real number unless it is your own test and the name starts with TEST.',
    ],
  },
  '/admin/hr/approvals': {
    text: 'Open Payroll Approvals. Do not approve a real payroll.',
    steps: [
      'Under Human Resources, click Payroll Approvals.',
      'The address should be /admin/hr/approvals.',
      'Waiting items or a clear empty message should show. Do not approve real wages.',
    ],
  },
  '/admin/hr/finance': {
    text: 'Open Finance Status.',
    steps: [
      'Under Human Resources, click Finance Status.',
      'The address should be /admin/hr/finance.',
      'A status or summary should show, or a clear empty message.',
    ],
  },
  '/admin/hr/reports': {
    text: 'Open HR Reports.',
    steps: [
      'Under Human Resources, click Reports.',
      'The address should be /admin/hr/reports.',
      'A report or a clear empty report should show. Do not delete data.',
    ],
  },
  '/admin/hr/letters/leave': {
    text: 'Open Leave of Absence. The letter form should show. Do not issue a letter for a real employee unless the name starts with TEST.',
    steps: [
      'In the blue menu, click HR Letters. The top bar should show Leave of Absence, Permission, Employment Letter, Attestation of Work, and Templates.',
      'Open Leave of Absence.',
      'The address should be /admin/hr/letters/leave. Fields for the staff member and dates should be visible.',
    ],
  },
  '/admin/hr/letters/permission': {
    text: 'Open the Permission letter.',
    steps: [
      'Under HR Letters, click Permission.',
      'The address should be /admin/hr/letters/permission.',
      'A letter form should show. Do not send it for a real employee unless the name starts with TEST.',
    ],
  },
  '/admin/hr/letters/employment': {
    text: 'Open Employment Letter.',
    steps: [
      'Under HR Letters, click Employment Letter.',
      'The address should be /admin/hr/letters/employment.',
      'A letter form should show. Do not send it for a real employee unless the name starts with TEST.',
    ],
  },
  '/admin/hr/letters/attestation': {
    text: 'Open Attestation of Work.',
    steps: [
      'Under HR Letters, click Attestation of Work.',
      'The address should be /admin/hr/letters/attestation.',
      'A letter form should show. Do not send it for a real employee unless the name starts with TEST.',
    ],
  },
  '/admin/hr/letters/templates': {
    text: 'Open HR letter Templates.',
    steps: [
      'Under HR Letters, click Templates.',
      'The address should be /admin/hr/letters/templates.',
      'Letter templates should be listed. Do not delete a template.',
    ],
  },
  '/admin/users': {
    text: 'Open All Users. The user list should show names or emails.',
    steps: [
      'In the blue menu, click Users. The top bar should show All Users, Add Customer, Customer List, Add Student, Student List, and ShareHolder.',
      'Open All Users.',
      'The address should be /admin/users. Do not delete a real user and do not change an administrator’s role.',
    ],
  },
  '/admin/users?action=customer': {
    text: 'Open Add Customer. The form should ask for a customer name. Do not save one unless the name starts with TEST.',
    steps: [
      'Under Users, click Add Customer.',
      'The address should include /admin/users and action=customer.',
      'Name and phone or email fields should be visible. Stop before saving, or use a name that starts with TEST.',
    ],
  },
  '/admin/users?filter=customer': {
    text: 'Open Customer List. Only customers should be emphasised, or the list should be clearly empty.',
    steps: [
      'Under Users, click Customer List.',
      'The address should include /admin/users and filter=customer.',
      'Do not delete a real customer.',
    ],
  },
  '/admin/students?action=new': {
    text: 'Open Add Student. Do not save a student unless the name starts with TEST.',
    steps: [
      'Under Users, click Add Student.',
      'The address should include /admin/students and action=new.',
      'A student form should show. Stop before saving, or use a name that starts with TEST.',
    ],
  },
  '/admin/students': {
    text: 'Open Student List.',
    steps: [
      'Under Users, click Student List.',
      'The address should be /admin/students.',
      'Students should be listed, or the empty message should be clear. Do not delete a real student.',
    ],
  },
  '/admin/shareholders/list': {
    text: 'Open the shareholder list from the Users menu. The same list is also under ShareHolders.',
    steps: [
      'Under Users, click ShareHolder. If you already opened List View under ShareHolders, you will see the same screen.',
      'The address should be /admin/shareholders/list.',
      'Shareholders should be listed, or the empty message should be clear. Do not delete a real shareholder.',
    ],
  },
  '/admin/members': {
    text: 'Open Member List. Team members should show, or a clear empty message.',
    steps: [
      'In the blue menu, click Members (Team), then Member List.',
      'The address should be /admin/members.',
      'Do not delete a real team member.',
    ],
  },
  '/admin/members?action=new': {
    text: 'Open Add Member. Do not save a member unless the name starts with TEST.',
    steps: [
      'Under Members (Team), click Add Member.',
      'The address should include /admin/members and action=new.',
      'A form for the member’s name should show. Stop before saving, or use a name that starts with TEST.',
    ],
  },
  '/admin/shareholders/dashboard': {
    text: 'Open the ShareHolders dashboard. The heading should say Shareholder Dashboard.',
    steps: [
      'In the blue menu, click ShareHolders. The top bar should show Dashboard, List View, Trash, Pending Approvals, Pending Payment, Signed Agreements, and Settings.',
      'Open Dashboard.',
      'The heading Shareholder Dashboard should show, with counts or cards. Do not pay real money.',
    ],
  },
  '/admin/shareholders/trash': {
    text: 'Open shareholder Trash. Do not permanently delete anyone.',
    steps: [
      'Under ShareHolders, click Trash.',
      'The address should be /admin/shareholders/trash.',
      'Trashed records or the words that trash is empty should show. Do not delete a record for good.',
    ],
  },
  '/admin/shareholders/pending-approvals': {
    text: 'Open Pending Approvals for shareholders. Do not approve a real investor by mistake.',
    steps: [
      'Under ShareHolders, click Pending Approvals.',
      'The address should be /admin/shareholders/pending-approvals.',
      'Waiting people or a clear empty message should show. Approve only a record whose name starts with TEST.',
    ],
  },
  '/admin/shareholders/pending-payments': {
    text: 'Open Pending Payment. Do not record a real payment.',
    steps: [
      'Under ShareHolders, click Pending Payment.',
      'The address should be /admin/shareholders/pending-payments.',
      'The list or empty message should be clear. Do not mark a real shareholder as paid.',
    ],
  },
  '/admin/shareholders/signed-agreements': {
    text: 'Open Signed Agreements.',
    steps: [
      'Under ShareHolders, click Signed Agreements.',
      'The address should be /admin/shareholders/signed-agreements.',
      'Signed agreements or a clear empty message should show. Do not delete one.',
    ],
  },
  '/admin/shareholders/settings': {
    text: 'Open shareholder Settings. This is the share price screen, not General Settings. Do not change the live price.',
    steps: [
      'Under ShareHolders, click Settings.',
      'The address should be /admin/shareholders/settings.',
      'You should see the share price or currency. Do not save a new price. This is not the system Settings menu.',
    ],
  },
  '/admin/reports': {
    text: 'Open Reports Hub.',
    steps: [
      'In the blue menu, open the System group, then click Reports Hub.',
      'The address should be /admin/reports.',
      'Report links or a report summary should show. Do not empty any data.',
    ],
  },
  '/admin/logs': {
    text: 'Open Activity Logs. Recent actions should be listed, or the page should say there are none.',
    steps: [
      'Under System, click Activity Logs.',
      'The address should be /admin/logs.',
      'Rows should show an action and a time, or a clear empty message.',
    ],
  },
  '/admin/history': {
    text: 'Open System History.',
    steps: [
      'Under System, click System History.',
      'The address should be /admin/history.',
      'History entries or a clear empty message should show.',
    ],
  },
  '/admin/help': {
    text: 'Open Help. It must be the last item in the blue menu, after System.',
    steps: [
      'Scroll the blue menu to the bottom. Help should be the last item, under its own Help heading, after Dashboard, the work menus, and System.',
      'Click Help. The address should be /admin/help.',
      'The page should explain this system in plain language, list the current public menu and the current admin menu, and link to this test and to finished test results.',
    ],
  },
};

function settingsCheck(entry) {
  return check(
    slugId(entry),
    `${entry.label} is part of system Settings. A test account must not see it, and the direct address must be refused. Do not empty the database.`,
    [
      'Sign in with the test account. Look through the blue menu, including the System group.',
      `${entry.label} should not be listed.`,
      `Type ${entry.path} in the address bar and press Enter.`,
      'The site should refuse the page. You must not see backup, mail, SMS, roles, or a control that empties the database.',
      SAFE,
    ],
    { settings: true },
  );
}

function adminCheck(entry) {
  if (entry.settings) return settingsCheck(entry);
  const detail = ADMIN_DETAIL[entry.path];
  const where = entry.section === entry.label ? entry.group : `${entry.group} → ${entry.section}`;
  if (!detail) {
    return check(
      slugId(entry),
      `Open ${entry.label}. You should see the ${entry.label} screen at ${entry.path}.`,
      [
        `Sign in. In the blue menu, open ${where}, then ${entry.label}.`,
        `The address should be ${entry.path}. The heading should match ${entry.label}.`,
        `The main area should show that screen’s list, form, or a clear empty message. It should not be an error page.`,
        SAFE,
      ],
    );
  }
  return check(slugId(entry), detail.text, [...detail.steps, SAFE]);
}

export function allChecks() {
  const admin = visibleAdminEntries().map((entry) => ({
    ...adminCheck(entry),
    section: entry.section === entry.label ? `${entry.group}: ${entry.label}` : `${entry.group}: ${entry.section} / ${entry.label}`,
  }));
  return [
    ...PUBLIC_CHECKS.map((item) => ({ ...item, section: 'Public website' })),
    ...LOGIN_CHECKS.map((item) => ({ ...item, section: 'Sign in' })),
    ...admin,
  ];
}

export function pages() {
  const checks = allChecks();
  const chunks = [];
  for (let i = 0; i < checks.length; i += PAGE_SIZE) {
    chunks.push(checks.slice(i, i + PAGE_SIZE));
  }
  return chunks.map((chunk, index) => ({
    number: index + 1,
    title: index === 0 ? 'Public website' : (chunk[0].settings ? 'Settings guard' : 'Checks'),
    checks: chunk,
  }));
}

export function checkMap() {
  const map = {};
  for (const item of allChecks()) map[item.id] = item;
  return map;
}

export function menuSnapshot() {
  return {
    publicMenu: visiblePublicMenu().map((item) => ({ label: item.label, path: item.path })),
    adminMenu: visibleAdminEntries().map((item) => ({
      group: item.group,
      section: item.section,
      label: item.label,
      path: item.path,
    })),
  };
}

export const SETTINGS_PATHS = [
  '/admin/general-settings',
  '/admin/settings',
  '/admin/system/general-settings',
  '/admin/backup-restore',
  '/admin/roles-permissions',
  '/admin/roles',
];

export function isSettingsPath(pathname) {
  const path = String(pathname || '').split('?')[0].replace(/\/$/, '') || '/';
  return SETTINGS_PATHS.some((prefix) => path === prefix || path.startsWith(`${prefix}/`));
}
