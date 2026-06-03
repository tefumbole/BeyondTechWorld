import React, { useState, useEffect } from 'react';
import { Outlet, Link, useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { isCurrentUserAdmin } from '@/services/whatsappAdminService';
import { 
  LayoutDashboard, 
  Users, 
  CreditCard, 
  LogOut, 
  Menu, 
  X, 
  Clock, 
  History, 
  FileBarChart, 
  ChevronDown, 
  Briefcase, 
  Settings, 
  CalendarDays, 
  PlusCircle, 
  BarChart, 
  CalendarClock, 
  PieChart, 
  Mail, 
  FileCheck, 
  Database, 
  BookOpen, 
  Award, 
  TrendingUp,
  Megaphone,
  PenLine,
  MessageSquare,
  FileText,
  ListTodo,
  CheckCircle,
  Key,
  Inbox,
  Ticket,
  QrCode,
  LineChart,
  Headphones,
  Music,
  Sliders,
  Utensils,
  ClipboardCheck
} from 'lucide-react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible';

const AdminLayout = () => {
  const { logout, user, profile } = useAuth();
  const navigate = useNavigate();
  const location = useLocation();
  const [sidebarOpen, setSidebarOpen] = useState(false);
  const [openMenus, setOpenMenus] = useState({});
  const [isAdmin, setIsAdmin] = useState(false);
  const [checkingAdmin, setCheckingAdmin] = useState(true);

  useEffect(() => {
    let isMounted = true;
    
    const checkAdmin = async () => {
      if (!user) {
        if (isMounted) {
          setIsAdmin(false);
          setCheckingAdmin(false);
        }
        return;
      }

      try {
        const adminStatus = await isCurrentUserAdmin();
        if (isMounted) {
          setIsAdmin(adminStatus);
        }
      } catch (error) {
        console.error("AdminLayout: Failed to check admin status", error);
        if (isMounted) setIsAdmin(false);
      } finally {
        if (isMounted) setCheckingAdmin(false);
      }
    };
    
    checkAdmin();
    
    return () => {
      isMounted = false;
    };
  }, [user]);

  const handleLogout = async () => {
    try {
      await logout();
      navigate('/admin/login');
    } catch (error) {
      console.error("Logout failed", error);
    }
  };

  const menuGroups = [
    {
      label: 'Overview',
      items: [
        { label: 'Dashboard', path: '/admin/dashboard', icon: LayoutDashboard },
      ]
    },
    {
      label: 'Audio Engineering',
      items: [
        {
          label: 'Audio Management',
          icon: Headphones,
          submenu: [
            { label: 'Audio Dashboard', path: '/admin/audio/dashboard', icon: LayoutDashboard },
            { label: 'Templates', path: '/admin/audio/templates', icon: Sliders },
            { label: 'Instruments', path: '/admin/audio/instruments', icon: Music },
            { label: 'Genres', path: '/admin/audio/genres', icon: FileText },
            { label: 'Mix Styles', path: '/admin/audio/styles', icon: FileText },
            { label: 'Keywords', path: '/admin/audio/keywords', icon: Settings },
            { label: 'Recommendations', path: '/admin/audio/recommendations', icon: Settings },
            { label: 'Access Control', path: '/admin/audio/access-control', icon: Key },
          ]
        }
      ]
    },
    {
      label: 'Digital Events',
      items: [
        {
          label: 'Event Management',
          icon: CalendarDays,
          submenu: [
            { label: 'Event Manager', path: '/admin/events', icon: CalendarDays },
            { label: 'Create Event', path: '/admin/events/create', icon: PlusCircle },
            { label: 'Meal Selections', path: '/admin/events/meal-selections', icon: Utensils },
            { label: 'Analytics', path: '/admin/events/analytics', icon: LineChart },
          ]
        },
        {
          label: 'Invitations & Entry',
          icon: Ticket,
          submenu: [
            { label: 'Create Invitation', path: '/admin/invitations/create', icon: PlusCircle },
            { label: 'All Invitations', path: '/admin/invitations', icon: ListTodo },
            { label: 'QR Check-In', path: '/admin/check-in', icon: QrCode },
          ]
        },
        {
          label: 'Templates & Config',
          icon: Settings,
          submenu: [
            { label: 'Design Templates', path: '/admin/events/templates', icon: FileText },
            { label: 'WhatsApp Templates', path: '/admin/events/wa-templates', icon: MessageSquare },
            { label: 'Webhook Settings', path: '/admin/events/webhooks', icon: Settings },
          ]
        }
      ]
    },
    {
      label: 'Work Management',
      items: [
        { 
          label: 'Task Management', 
          icon: ListTodo, 
          submenu: [
            { label: 'Task Dashboard', path: '/admin/tasks/dashboard', icon: LayoutDashboard },
            { label: 'All Tasks', path: '/admin/tasks', icon: ListTodo },
            { label: 'Create Task', path: '/admin/tasks/create', icon: PlusCircle },
            { label: 'Task Settings', path: '/admin/tasks/settings', icon: Settings },
            { label: 'My Tasks', path: '/user/tasks', icon: CheckCircle },
            { label: 'Pending Acceptances', path: '/user/tasks/pending-acceptances', icon: Inbox }
          ]
        },
        { 
          label: 'Job Board', 
          icon: Briefcase, 
          submenu: [
            { label: 'Recruitment Dashboard', path: '/admin/recruitment-dashboard' },
            { label: 'Manage Jobs', path: '/admin/jobs' },
            { label: 'All Applications', path: '/admin/applications' },
            { label: 'Shortlisted', path: '/admin/applications/shortlisted' },
            { label: 'Rejected', path: '/admin/applications/rejected' },
          ]
        },
      ]
    },
    {
      label: 'People & Access',
      items: [
        { 
          label: 'Users', 
          icon: Users, 
          submenu: [
            { label: 'User List', path: '/admin/users' },
            { label: 'Students', path: '/admin/students' },
          ]
        },
        { 
          label: 'Members (Team)', 
          icon: Users, 
          submenu: [
            { label: 'Member List', path: '/admin/members' },
            { label: 'Add Member', path: '/admin/members?action=new' }, 
          ]
        },
        { 
          label: 'ShareHolders', 
          icon: PieChart, 
          submenu: [
            { label: 'Dashboard', path: '/admin/shareholders/dashboard' },
            { label: 'List View', path: '/admin/shareholders/list' },
            { label: 'Pending Approvals', path: '/admin/shareholders/pending-approvals', icon: ClipboardCheck },
            { label: 'Signed Agreements', path: '/admin/shareholders/signed-agreements', icon: FileCheck },
            { label: 'Settings', path: '/admin/shareholders/settings' }
          ]
        },
      ]
    },
    {
      label: 'Learning & Content',
      items: [
        {
          label: 'Courses',
          icon: BookOpen,
          submenu: [
            { label: 'Course List', path: '/admin/courses' },
            { label: 'Add Course', path: '/admin/courses/add' },
            { label: 'Registrations', path: '/admin/registrations' },
            { label: 'Invoices', path: '/admin/invoices', icon: FileText },
            { label: 'Certificates', path: '/admin/certificates', icon: Award },
            { label: 'Student Progress', path: '/admin/progress', icon: TrendingUp },
            { label: 'Feedback', path: '/admin/feedback', icon: MessageSquare },
          ]
        }
      ]
    },
    {
      label: 'Communication & Messaging',
      items: [
        {
          label: 'Announcements',
          icon: Megaphone,
          submenu: [
            { label: 'Compose', path: '/admin/announcements/compose', icon: PenLine },
            { label: 'All Announcements', path: '/admin/announcements/list', icon: FileText },
            { label: 'Scheduled', path: '/admin/announcements/scheduled', icon: Clock },
            { label: 'Settings', path: '/admin/announcements/settings', icon: Settings },
          ]
        },
        {
          label: 'Legacy Comm.',
          icon: Mail,
          submenu: [
            { label: 'Send Notification', path: '/admin/communication/notifications' },
            { label: 'Create Letter', path: '/admin/communication/letters' },
            { label: 'WhatsApp Messages', path: '/admin/whatsapp-messages', icon: MessageSquare },
            { label: 'Categories', path: '/admin/communication/categories' },
            { label: 'Comm. Settings', path: '/admin/communication/settings' }
          ]
        },
      ]
    },
    {
      label: 'TimeSheets (Employee)',
      items: [
        { label: 'Create Activity', path: '/timesheet/create-activity', icon: PlusCircle },
        { label: 'Fill Time Sheet', path: '/timesheet/fill-timesheet', icon: Clock },
        { label: 'Working Week', path: '/timesheet/working-week', icon: CalendarClock },
      ]
    },
    {
      label: 'Operations',
      items: [
        { 
          label: 'TimeSheet Admin', 
          icon: BarChart, 
          submenu: [
            { label: 'TimeSheet Report', path: '/admin/timesheet-report' },
            { label: 'Overtime Report', path: '/admin/overtime-report' },
            { label: 'Manage All', path: '/admin/manage-timesheets' },
            { label: 'Categories', path: '/admin/timesheet-categories' } 
          ]
        },
        { label: 'Payments', path: '/admin/payments', icon: CreditCard }, 
      ]
    },
    {
      label: 'System',
      items: [
        { label: 'Reports Hub', path: '/admin/reports', icon: FileBarChart },
        { label: 'Contact Messages', path: '/admin/contact-messages', icon: MessageSquare },
        { label: 'Backup & Restore', path: '/admin/backup-restore', icon: Database },
        { label: 'Settings', path: '/admin/settings', icon: Settings },
        { label: 'System History', path: '/admin/history', icon: History },
      ]
    }
  ];

  const toggleMenu = (label) => {
    setOpenMenus(prev => ({
      ...prev,
      [label]: !prev[label]
    }));
  };

  const MenuItem = ({ item }) => {
    const isActive = item.submenu 
      ? item.submenu.some(sub => location.pathname.startsWith(sub.path))
      : (item.path ? location.pathname.startsWith(item.path) : false);

    const isOpen = openMenus[item.label] || isActive;

    if (item.submenu) {
      return (
        <Collapsible open={isOpen} onOpenChange={() => toggleMenu(item.label)} className="w-full">
          <CollapsibleTrigger className={cn(
            "flex items-center justify-between w-full px-4 py-3 rounded-lg transition-all duration-200 text-gray-100 hover:bg-white/10 hover:text-white",
            isActive && "bg-[#003066]"
          )}>
            <div className="flex items-center gap-3">
              <item.icon className="w-5 h-5 text-[#D4AF37]" />
              <span>{item.label}</span>
            </div>
            <ChevronDown className={cn("w-4 h-4 transition-transform", isOpen && "transform rotate-180")} />
          </CollapsibleTrigger>
          <CollapsibleContent className="pl-12 space-y-1 pt-1">
            {item.submenu.map(sub => (
              <Link 
                key={sub.path} 
                to={sub.path}
                onClick={() => setSidebarOpen(false)}
                className={cn(
                  "flex items-center gap-2 py-2 px-3 rounded text-sm transition-colors",
                  location.pathname === sub.path ? "text-[#D4AF37] font-medium bg-white/5" : "text-gray-400 hover:text-white"
                )}
              >
                {sub.icon && <sub.icon className="w-3 h-3" />}
                {sub.label}
              </Link>
            ))}
          </CollapsibleContent>
        </Collapsible>
      );
    }

    return (
      <Link 
        to={item.path}
        onClick={() => setSidebarOpen(false)}
        className={cn(
          "flex items-center gap-3 px-4 py-3 rounded-lg transition-all duration-200 group relative overflow-hidden",
          location.pathname === item.path
            ? "bg-[#D4AF37] text-[#003D82] font-bold shadow-md" 
            : "text-gray-100 hover:bg-white/10 hover:text-white"
        )}
      >
        <item.icon className={cn("w-5 h-5 transition-transform group-hover:scale-110", location.pathname === item.path ? "text-[#003D82]" : "text-[#D4AF37]")} />
        <span className="relative z-10">{item.label}</span>
      </Link>
    );
  };

  return (
    <div className="min-h-screen bg-gray-50 flex flex-col md:flex-row">
      <div className="md:hidden bg-[#003D82] text-white p-4 flex justify-between items-center z-20 sticky top-0 shadow-md">
        <div className="flex items-center gap-2">
          <span className="font-bold text-lg text-[#D4AF37]">Alpha Admin</span>
        </div>
        <button onClick={() => setSidebarOpen(!sidebarOpen)} className="p-2">
          {sidebarOpen ? <X className="w-6 h-6" /> : <Menu className="w-6 h-6" />}
        </button>
      </div>

      <aside className={cn(
        "fixed md:sticky top-0 h-screen bg-[#003D82] text-white w-64 transform transition-transform duration-200 ease-in-out z-10 flex flex-col shadow-2xl overflow-hidden",
        sidebarOpen ? "translate-x-0" : "-translate-x-full md:translate-x-0"
      )}>
        <div className="p-6 border-b border-[#D4AF37]/30 bg-[#002855] shrink-0">
          <h1 className="text-2xl font-bold text-[#D4AF37]">Alpha Bridge</h1>
          <p className="text-xs text-gray-300 mt-1 uppercase tracking-widest">Technologies Ltd</p>
        </div>

        <nav className="flex-1 p-4 space-y-6 overflow-y-auto scrollbar-thin scrollbar-thumb-blue-800">
          {menuGroups.map((group, idx) => (
            <div key={idx}>
              <h3 className="text-xs font-bold text-gray-400 uppercase tracking-wider mb-2 px-4">{group.label}</h3>
              <div className="space-y-1">
                {group.items.map((item, i) => <MenuItem key={i} item={item} />)}
              </div>
            </div>
          ))}
        </nav>

        <div className="p-4 bg-[#002244] border-t border-[#D4AF37]/30 shrink-0">
          <div className="mb-4 flex items-center gap-3 px-2">
            <div className="h-8 w-8 rounded-full bg-[#D4AF37] flex items-center justify-center text-[#003D82] font-bold">
              {user?.email ? user.email.charAt(0).toUpperCase() : 'A'}
            </div>
            <div className="overflow-hidden">
              <p className="text-xs text-gray-200 font-medium truncate">
                {profile?.full_name || user?.email || 'Administrator'}
              </p>
              <div className="text-[10px] px-1.5 py-0.5 rounded mt-1 inline-block bg-purple-500/20 text-purple-300 border border-purple-500/30">
                {checkingAdmin ? 'Checking...' : (isAdmin ? 'Administrator' : 'User')}
              </div>
            </div>
          </div>
          
          <Button 
            onClick={handleLogout} 
            variant="ghost" 
            className="w-full justify-start text-red-300 hover:text-white hover:bg-red-600/80 transition-colors"
          >
            <LogOut className="w-5 h-5 mr-2" />
            Sign Out
          </Button>
        </div>
      </aside>

      {sidebarOpen && (
        <div 
          className="fixed inset-0 bg-black/50 z-0 md:hidden backdrop-blur-sm"
          onClick={() => setSidebarOpen(false)}
        ></div>
      )}

      <main className="flex-1 p-4 md:p-8 overflow-y-auto bg-gray-50 h-[calc(100vh-64px)] md:h-screen">
        <div className="max-w-7xl mx-auto pb-10 print:p-0 print:m-0 print:max-w-none print:w-full print:bg-white">
          <Outlet />
        </div>
      </main>
    </div>
  );
};

export default AdminLayout;