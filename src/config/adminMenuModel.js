/**
 * Live admin side menu. Help is not listed here.
 * withHelpLast() always places Help after every other item.
 * settings: true marks the system settings area a test account must not open.
 */
export const ADMIN_MENU_GROUPS = [
  {
    label: 'Dashboard',
    items: [
      { label: 'Dashboard', path: '/admin/dashboard', icon: 'LayoutDashboard', permission: 'menu.dashboard' },
    ],
  },
  {
    label: 'Work Management',
    items: [
      {
        label: 'Task Management',
        icon: 'ListTodo',
        permission: 'menu.tasks',
        submenu: [
          { label: 'Task Dashboard', path: '/admin/tasks/dashboard', icon: 'LayoutDashboard' },
          { label: 'Create Task', path: '/admin/tasks/create', icon: 'PlusCircle' },
          { label: 'All Tasks', path: '/admin/tasks', icon: 'ListTodo' },
          { label: 'Scheduled', path: '/admin/tasks/scheduled', icon: 'CalendarClock' },
          { label: 'Reminders', path: '/admin/tasks/reminders', icon: 'Clock' },
          { label: 'My Tasks', path: '/admin/tasks/my-tasks', icon: 'CheckCircle' },
          { label: 'Pending Acceptances', path: '/admin/tasks/pending-acceptances', icon: 'Inbox' },
          { label: 'Task Settings', path: '/admin/tasks/settings', icon: 'Settings' },
        ],
      },
      {
        label: 'Job Board',
        icon: 'Briefcase',
        permission: 'menu.jobs',
        submenu: [
          { label: 'Recruitment Dashboard', path: '/admin/recruitment-dashboard' },
          { label: 'Manage Jobs', path: '/admin/jobs' },
          { label: 'All Applications', path: '/admin/applications' },
          { label: 'Shortlisted', path: '/admin/applications/shortlisted' },
          { label: 'Rejected', path: '/admin/applications/rejected' },
        ],
      },
      { label: 'Event Management', path: '/admin/events', icon: 'CalendarDays', permission: 'menu.events' },
      { label: 'Digital Invitations', path: '/admin/invitations', icon: 'Ticket', permission: 'menu.invitations', activePaths: ['/admin/invitations', '/admin/check-in'] },
      { label: 'Event Templates & Config', path: '/admin/events/templates', icon: 'Settings', permission: 'menu.event_templates', activePaths: ['/admin/events/templates', '/admin/events/wa-templates', '/admin/events/webhooks'] },
    ],
  },
  {
    label: 'Communication & Messaging',
    items: [
      {
        label: 'Announcements',
        icon: 'Megaphone',
        permission: 'menu.announcements',
        submenu: [
          { label: 'Compose', path: '/admin/announcements/compose', icon: 'PenLine' },
          { label: 'All Announcements', path: '/admin/announcements/list', icon: 'FileText' },
          { label: 'Scheduled', path: '/admin/announcements/scheduled', icon: 'Clock' },
          { label: 'Templates', path: '/admin/announcements/templates', icon: 'FileText' },
          { label: 'Categories', path: '/admin/announcements/categories', icon: 'FileText' },
          { label: 'Settings', path: '/admin/announcements/settings', icon: 'Settings' },
        ],
      },
    ],
  },
  {
    label: 'Time & Attendance',
    items: [
      {
        label: 'TimeSheets (Employee)',
        icon: 'Clock',
        permission: 'menu.timesheets',
        submenu: [
          { label: 'Create Activity', path: '/admin/timesheet/create-activity', icon: 'PlusCircle' },
          { label: 'Fill Time Sheet', path: '/admin/timesheet/fill-timesheet', icon: 'Clock' },
          { label: 'Working Week', path: '/admin/timesheet/working-week', icon: 'CalendarClock' },
        ],
      },
    ],
  },
  {
    label: 'Operations',
    items: [
      {
        label: 'TimeSheet Admin',
        icon: 'BarChart',
        permission: 'menu.operations',
        submenu: [
          { label: 'TimeSheet Report', path: '/admin/timesheet-report' },
          { label: 'Overtime Report', path: '/admin/overtime-report' },
          { label: 'Manage All', path: '/admin/manage-timesheets' },
          { label: 'Categories', path: '/admin/timesheet-categories' },
        ],
      },
      { label: 'Payments', path: '/admin/payments', icon: 'CreditCard', permission: 'menu.operations' },
    ],
  },
  {
    label: 'Courses',
    items: [
      {
        label: 'Courses',
        icon: 'BookOpen',
        permission: 'menu.courses',
        submenu: [
          { label: 'Course List', path: '/admin/courses' },
          { label: 'Add Course', path: '/admin/courses/add' },
          { label: 'Registrations', path: '/admin/registrations' },
          { label: 'Invoices', path: '/admin/invoices', icon: 'FileText' },
          { label: 'Certificates', path: '/admin/certificates', icon: 'Award' },
          { label: 'Student Progress', path: '/admin/progress', icon: 'TrendingUp' },
          { label: 'Feedback', path: '/admin/feedback', icon: 'MessageSquare' },
        ],
      },
    ],
  },
  {
    label: 'HR & Payroll',
    items: [
      {
        label: 'Human Resources',
        icon: 'Wallet',
        permission: 'menu.hr',
        submenu: [
          { label: 'Staff Management', path: '/admin/hr/staff' },
          { label: 'Staff Categories', path: '/admin/hr/categories' },
          { label: 'Job / Event Payroll', path: '/admin/hr/jobs' },
          { label: 'Monthly Payroll', path: '/admin/hr/monthly-payroll' },
          { label: 'Allowances', path: '/admin/hr/allowances' },
          { label: 'Deductions', path: '/admin/hr/deductions' },
          { label: 'Advance Payments', path: '/admin/hr/advances' },
          { label: 'Payslips', path: '/admin/hr/payslips' },
          { label: 'Payroll Approvals', path: '/admin/hr/approvals' },
          { label: 'Finance Status', path: '/admin/hr/finance' },
          { label: 'Reports', path: '/admin/hr/reports' },
        ],
      },
      {
        label: 'HR Letters',
        icon: 'FileText',
        permission: 'menu.hr',
        submenu: [
          { label: 'Leave of Absence', path: '/admin/hr/letters/leave' },
          { label: 'Permission', path: '/admin/hr/letters/permission' },
          { label: 'Employment Letter', path: '/admin/hr/letters/employment' },
          { label: 'Attestation of Work', path: '/admin/hr/letters/attestation' },
          { label: 'Templates', path: '/admin/hr/letters/templates' },
        ],
      },
    ],
  },
  {
    label: 'People & Access',
    items: [
      {
        label: 'Users',
        icon: 'Users',
        permission: 'menu.users',
        submenu: [
          { label: 'All Users', path: '/admin/users' },
          { label: 'Add Customer', path: '/admin/users?action=customer', icon: 'UserPlus' },
          { label: 'Customer List', path: '/admin/users?filter=customer' },
          { label: 'Add Student', path: '/admin/students?action=new', icon: 'UserPlus' },
          { label: 'Student List', path: '/admin/students' },
          { label: 'ShareHolder', path: '/admin/shareholders/list', icon: 'PieChart' },
        ],
      },
      {
        label: 'Members (Team)',
        icon: 'Users',
        permission: 'menu.members',
        submenu: [
          { label: 'Member List', path: '/admin/members' },
          { label: 'Add Member', path: '/admin/members?action=new' },
        ],
      },
      {
        label: 'ShareHolders',
        icon: 'PieChart',
        permission: 'menu.shareholders',
        submenu: [
          { label: 'Dashboard', path: '/admin/shareholders/dashboard' },
          { label: 'List View', path: '/admin/shareholders/list' },
          { label: 'Trash', path: '/admin/shareholders/trash', icon: 'Trash2' },
          { label: 'Pending Approvals', path: '/admin/shareholders/pending-approvals', icon: 'ClipboardCheck' },
          { label: 'Pending Payment', path: '/admin/shareholders/pending-payments', icon: 'CreditCard' },
          { label: 'Signed Agreements', path: '/admin/shareholders/signed-agreements', icon: 'FileCheck' },
          { label: 'Settings', path: '/admin/shareholders/settings' },
        ],
      },
    ],
  },
  {
    label: 'System',
    collapsible: true,
    permission: 'menu.system',
    items: [
      { label: 'Reports Hub', path: '/admin/reports', icon: 'FileBarChart' },
      { label: 'Activity Logs', path: '/admin/logs', icon: 'ScrollText' },
      { label: 'Backup & Restore', path: '/admin/backup-restore', icon: 'Database', settings: true },
      { label: 'General Settings', path: '/admin/general-settings', icon: 'Settings', settings: true },
      { label: 'Roles & Permissions', path: '/admin/roles-permissions', icon: 'Key', permission: 'menu.roles', settings: true },
      { label: 'System History', path: '/admin/history', icon: 'History' },
    ],
  },
];

export const HELP_MENU_ITEM = {
  label: 'Help',
  path: '/admin/help',
  icon: 'CircleHelp',
};

export function withHelpLast(groups = ADMIN_MENU_GROUPS) {
  const cleaned = (groups || [])
    .map((group) => ({
      ...group,
      items: (group.items || [])
        .filter((item) => item.path !== HELP_MENU_ITEM.path && item.label !== 'Help')
        .map((item) => ({
          ...item,
          submenu: item.submenu
            ? item.submenu.filter((sub) => sub.path !== HELP_MENU_ITEM.path && sub.label !== 'Help')
            : item.submenu,
        })),
    }))
    .filter((group) => (group.items || []).length);

  cleaned.push({
    label: 'Help',
    items: [{ ...HELP_MENU_ITEM }],
  });
  return cleaned;
}

/** Flat visible admin entries in saved order. Hidden items are omitted. Help stays last. */
export function visibleAdminEntries({ hideSettings = false } = {}) {
  const entries = [];
  for (const group of withHelpLast()) {
    if (group.hidden) continue;
    for (const item of group.items || []) {
      if (item.hidden) continue;
      if (hideSettings && (item.settings || group.settings)) continue;
      if (item.submenu?.length) {
        for (const sub of item.submenu) {
          if (sub.hidden) continue;
          if (hideSettings && sub.settings) continue;
          entries.push({
            group: group.label,
            section: item.label,
            label: sub.label,
            path: sub.path,
            settings: Boolean(sub.settings),
          });
        }
      } else if (item.path) {
        entries.push({
          group: group.label,
          section: item.label,
          label: item.label,
          path: item.path,
          settings: Boolean(item.settings),
        });
      }
    }
  }
  return entries;
}
