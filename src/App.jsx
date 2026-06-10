import React, { useEffect, useState } from 'react';
import { BrowserRouter, Routes, Route, Navigate, Outlet, useLocation } from 'react-router-dom';
import ScrollToTop from '@/components/ScrollToTop';
import MainLayout from '@/layouts/MainLayout';
import TaskAssigneeLayout from '@/layouts/TaskAssigneeLayout';
import ErrorBoundary from '@/components/ErrorBoundary';
import { debugEnv } from '@/utils/debug';
import { autoStartWorker } from '@/services/queueWorkerService';
import { refreshSessionIfNeeded } from '@/utils/sessionUtils';

// Modals
import LoginModal from '@/components/LoginModal';
import SignUpModal from '@/components/SignUpModal';

// Public Pages
import HomePage from '@/pages/HomePage';
import AboutPage from '@/pages/AboutPage';
import ServicesPage from '@/pages/ServicesPage';
import ContactUsPage from '@/pages/public/ContactUsPage';
import ProjectsPage from '@/pages/ProjectsPage';
import TrainingsPage from '@/pages/TrainingsPage';
import EventsPage from '@/pages/EventsPage';
import EventDetailsPage from '@/pages/EventDetailsPage';
import RegistrationPage from '@/pages/RegistrationPage';
import RegisterNowPage from '@/pages/RegisterNowPage';
import ShareholdersPage from '@/pages/ShareholdersPage';
import MenuSelectionPage from '@/pages/public/MenuSelectionPage';

// Share Imports
import SharesPage from '@/pages/SharesPage';
import ShareholderConfirmationPage from '@/components/ShareholderConfirmationPage';
import AdminShareHolderListPage from '@/pages/AdminShareHolderListPage';
import ShareholdersAgreementPage from '@/pages/ShareholdersAgreementPage';
import SignedAgreementVerifyPage from '@/pages/SignedAgreementVerifyPage';
import SharePurchasePortal from '@/pages/SharePurchasePortal';
import QRScannerPage from '@/pages/QRScannerPage';
import StudentProgressPage from '@/pages/StudentProgressPage';

// Job Application Pages
import ApplyNowPage from '@/pages/ApplyNowPage'; 
import JobApplicationFormPage from '@/pages/JobApplicationFormPage';
import ApplicationConfirmationPage from '@/pages/ApplicationConfirmationPage';

// Authentication & Portals
import LoginPage from '@/pages/LoginPage';
import ForgotPasswordPage from '@/pages/ForgotPasswordPage';
import OTPVerificationScreen from '@/pages/OTPVerificationScreen';
import StudentDashboard from '@/pages/StudentDashboard';
import ShareholderDashboard from '@/pages/ShareholderDashboard';
import ApplicantDashboard from '@/pages/ApplicantDashboard';
import MyTasksPage from '@/pages/user/MyTasksPage'; 
import PendingAcceptancesPage from '@/pages/user/PendingAcceptancesPage';
import AdminShareHolderTrashPage from '@/pages/admin/shareholders/AdminShareHolderTrashPage';
import TaskInvitePage from '@/pages/TaskInvitePage'; 

// Components
import WhatsAppModal from '@/components/WhatsAppModal';
import { Toaster } from '@/components/ui/toaster';
import { Toaster as SonnerToaster } from 'sonner';
import MemberSignupForm from '@/components/MemberSignupForm';
import { Loader2 } from 'lucide-react';

// Context Providers
import { WhatsAppProvider } from '@/context/WhatsAppContext';
import { AuthProvider, useAuth } from '@/context/AuthContext';
import { TimeSheetProvider } from '@/context/TimeSheetContext';
import { PermissionProvider } from '@/context/PermissionContext';

// Admin & Security
import ProtectedRoute from '@/components/ProtectedRoute';
import AccessDeniedPage from '@/components/AccessDeniedPage';
import AdminLayout from '@/layouts/AdminLayout';

// Admin Pages
import AdminDashboard from '@/pages/admin/AdminDashboard';
import AdminStudentsPage from '@/pages/admin/AdminStudentsPage';
import AdminPaymentsPage from '@/pages/admin/AdminPaymentsPage';
import AdminUsersPage from '@/pages/admin/AdminUsersPage'; 
import AdminRolesPage from '@/pages/admin/AdminRolesPage';
import AdminSettingsPage from '@/pages/admin/AdminSettingsPage';
import GeneralSystemSettingsPage from '@/pages/admin/GeneralSystemSettingsPage';
import AdminMembersPage from '@/pages/admin/AdminMembersPage';
import AdminCoursesPage from '@/pages/admin/AdminCoursesPage';
import AdminRegistrationsPage from '@/pages/admin/AdminRegistrationsPage';
import AdminShareListingPage from '@/pages/admin/AdminShareListingPage';
import AdminInvoicesPage from '@/pages/admin/AdminInvoicesPage';
import AdminCertificatesPage from '@/pages/admin/AdminCertificatesPage';
import AdminProgressPage from '@/pages/admin/AdminProgressPage';
import AdminFeedbackPage from '@/pages/admin/AdminFeedbackPage';
import AdminReportsPage from '@/pages/admin/AdminReportsPage';
import AdminHistoryPage from '@/pages/admin/AdminHistoryPage';
import ContactMessagesPage from '@/pages/admin/ContactMessagesPage';

// Jobs Admin Pages
import AdminJobsPage from '@/pages/admin/AdminJobsPage';
import AdminApplicationListPage from '@/pages/admin/AdminApplicationListPage';
import AdminJobApplicationDashboard from '@/pages/admin/AdminJobApplicationDashboard';
import AdminRejectedApplicationsPage from '@/pages/admin/AdminRejectedApplicationsPage';
import AdminShortlistedApplicationsPage from '@/pages/admin/AdminShortlistedApplicationsPage';

// Shareholder Admin Pages
import AdminShareHolderDashboardPage from '@/pages/admin/AdminShareHolderDashboardPage';
import AdminShareSettingsPage from '@/pages/admin/AdminShareSettingsPage';
import ShareSignedSharesPage from '@/pages/admin/ShareSignedSharesPage';
import PendingShareApprovalsPage from '@/pages/admin/shareholders/PendingShareApprovalsPage';
import SignedAgreementsPage from '@/pages/admin/shareholders/SignedAgreementsPage';
import PendingPaymentsPage from '@/pages/admin/shareholders/PendingPaymentsPage';
import SharesListPage from '@/pages/admin/shareholders/SharesListPage';

// Communication Admin Pages
import AdminCommunicationCategoriesPage from '@/pages/admin/AdminCommunicationCategoriesPage';
import AdminNotificationComposerPage from '@/pages/admin/AdminNotificationComposerPage';
import AdminLetterComposerPage from '@/pages/admin/AdminLetterComposerPage';
import AdminCommunicationSettingsPage from '@/pages/admin/AdminCommunicationSettingsPage';
import WhatsAppMessageHistoryPage from '@/pages/admin/WhatsAppMessageHistoryPage';
import MessageSettingsPage from '@/pages/admin/MessageSettingsPage';
import ComposeMessagePage from '@/pages/admin/ComposeMessagePage';
import QueueListingPage from '@/pages/admin/QueueListingPage';
import MailListingPage from '@/pages/admin/MailListingPage';
import AnnouncementsManagementPage from '@/pages/admin/AnnouncementsManagementPage';
import AnnouncementSettingsPage from '@/pages/admin/AnnouncementSettingsPage';
import TemplateManagementPage from '@/pages/admin/TemplateManagementPage';

// Time Sheet Admin Pages
import AdminTimeSheetManagementPage from '@/pages/admin/AdminTimeSheetManagementPage';
import AdminTimeSheetReportPage from '@/pages/admin/AdminTimeSheetReportPage';
import AdminOvertimeReportPage from '@/pages/admin/AdminOvertimeReportPage';
import AdminTimeSheetCategoriesPage from '@/pages/admin/AdminTimeSheetCategoriesPage';

// System Admin
import AdminBackupRestorePage from '@/pages/admin/AdminBackupRestorePage';
import RolesPermissionsPage from '@/pages/admin/RolesPermissionsPage';

// Employee Time Sheet Pages
import MonthlyHoursSummaryPage from '@/pages/timesheet/MonthlyHoursSummaryPage';
import ActivityManagementPage from '@/pages/timesheet/ActivityManagementPage';
import FillTimeSheetPage from '@/pages/timesheet/FillTimeSheetPage';
import WorkingWeekPage from '@/pages/timesheet/WorkingWeekPage';

// Task Management Admin Pages
import TaskDashboardPage from '@/pages/admin/TaskDashboardPage';
import AdminTaskListPage from '@/pages/admin/AdminTaskListPage';
import ScheduledTasksPage from '@/pages/admin/ScheduledTasksPage';
import CreateTaskPage from '@/pages/admin/CreateTaskPage';
import TaskSettingsPage from '@/pages/admin/TaskSettingsPage';

// --- NEW DIGITAL EVENTS IMPORTS ---
import EventManagerPage from '@/pages/admin/events/EventManagerPage';
import CreateEventPage from '@/pages/admin/events/CreateEventPage';
import CreateInvitationPage from '@/pages/admin/events/CreateInvitationPage';
import InvitationListPage from '@/pages/admin/InvitationListPage';
import InvitationDetailPage from '@/pages/admin/InvitationDetailPage';
import QRCheckInPage from '@/pages/admin/events/QRCheckInPage';
import DesignTemplatesPage from '@/pages/admin/events/DesignTemplatesPage';
import PlaceholderPage from '@/pages/admin/events/PlaceholderPage';
import WhatsAppTemplatesPage from '@/pages/admin/WhatsAppTemplatesPage';
import WebhookSettingsPage from '@/pages/admin/WebhookSettingsPage';
import MealsListPage from '@/pages/admin/events/MealsListPage';
import CreateMealPage from '@/pages/admin/events/CreateMealPage';
import AnnouncementCategoriesPage from '@/pages/admin/AnnouncementCategoriesPage';

// --- AUDIO MIXING ASSISTANT ADMIN IMPORTS ---
import AudioAdminDashboard from '@/pages/admin/audio/AudioAdminDashboard';
import InstrumentsManagement from '@/pages/admin/audio/InstrumentsManagement';
import GenresManagement from '@/pages/admin/audio/GenresManagement';
import MixStylesManagement from '@/pages/admin/audio/MixStylesManagement';
import MixingTemplatesManagement from '@/pages/admin/audio/MixingTemplatesManagement';
import InstrumentKeywordsManagement from '@/pages/admin/audio/InstrumentKeywordsManagement';
import RecommendationRules from '@/pages/admin/audio/RecommendationRules';
import AudioAdminAccessControl from '@/pages/admin/audio/AudioAdminAccessControl';

// --- AUDIO MIXING ASSISTANT USER IMPORTS ---
import MixingTemplatesLibrary from '@/pages/audio/MixingTemplatesLibrary';
import TemplateDetailPage from '@/pages/audio/TemplateDetailPage';
import AIMixingAssistant from '@/pages/audio/AIMixingAssistant';
import MixSessions from '@/pages/audio/MixSessions';
import TemplateFavorites from '@/pages/audio/TemplateFavorites';
import UsageHistory from '@/pages/audio/UsageHistory';

// --- EVENTS MANAGEMENT ADMIN IMPORT ---
import EventManagementPage from '@/pages/admin/EventManagementPage';

import { validateConfig } from '@/utils/configValidator';

// Session validation interval: 10 minutes
const SESSION_VALIDATION_INTERVAL = 10 * 60 * 1000;

const LayoutContextWrapper = () => {
  const [isLoginOpen, setIsLoginOpen] = useState(false);
  const [isSignUpOpen, setIsSignUpOpen] = useState(false);
  const [redirectPath, setRedirectPath] = useState(null);

  const openLogin = (redirect) => {
    if (redirect) setRedirectPath(redirect);
    setIsLoginOpen(true);
  };

  const openSignUp = (redirect) => {
    if (redirect) setRedirectPath(redirect);
    setIsSignUpOpen(true);
  };

  const contextValue = {
    openLoginModal: openLogin,
    openSignUpModal: openSignUp,
  };

  return (
    <>
      <LoginModal
        isOpen={isLoginOpen}
        onClose={() => {
          setIsLoginOpen(false);
          setRedirectPath(null);
        }}
        onSwitchToSignUp={() => {
          setIsLoginOpen(false);
          setIsSignUpOpen(true);
        }}
        redirectOnSuccess={redirectPath}
      />
      <SignUpModal
        isOpen={isSignUpOpen}
        onClose={() => {
          setIsSignUpOpen(false);
          setRedirectPath(null);
        }}
        onSwitchToLogin={() => {
          setIsSignUpOpen(false);
          setIsLoginOpen(true);
        }}
        redirectOnSuccess={redirectPath}
      />
      <MainLayout>
        <Outlet context={contextValue} />
      </MainLayout>
    </>
  );
};

// Loading screen component
const AppLoadingScreen = () => (
  <div className="min-h-screen bg-gradient-to-br from-[#003D82] to-[#001f42] flex items-center justify-center">
    <div className="text-center">
      <div className="mb-6">
        <Loader2 className="w-16 h-16 animate-spin text-[#D4AF37] mx-auto" />
      </div>
      <h2 className="text-2xl font-bold text-white mb-2">Alpha Bridge Technologies</h2>
      <p className="text-gray-300">Initializing application...</p>
    </div>
  </div>
);

// Main app content with auth check
const AUTH_LOADING_MAX_MS = 6000;

const isProtectedPath = (pathname) =>
  pathname.startsWith('/admin') ||
  pathname.startsWith('/student') ||
  pathname.startsWith('/shareholder') ||
  pathname.startsWith('/applicant') ||
  pathname.startsWith('/user/') ||
  pathname.startsWith('/timesheet');

const AppContent = () => {
  const { loading: authLoading } = useAuth();
  const location = useLocation();
  const [showAnyway, setShowAnyway] = useState(false);

  useEffect(() => {
    if (!authLoading) {
      setShowAnyway(false);
      return undefined;
    }
    const timer = setTimeout(() => setShowAnyway(true), AUTH_LOADING_MAX_MS);
    return () => clearTimeout(timer);
  }, [authLoading, location.pathname]);

  const mustWaitForAuth = isProtectedPath(location.pathname);

  if (authLoading && mustWaitForAuth && !showAnyway) {
    return <AppLoadingScreen />;
  }

  return (
    <>
      <ScrollToTop />
      <WhatsAppModal />

      <Routes>
        {/* Public Website Routes */}
        <Route element={<LayoutContextWrapper />}>
          <Route path="/" element={<HomePage />} />
          <Route path="services" element={<Navigate to="/trainings" replace />} />
          <Route path="trainings" element={<TrainingsPage />} />
          <Route path="events" element={<EventsPage />} />
          <Route path="events/:eventId" element={<EventDetailsPage />} />
          <Route path="about" element={<AboutPage />} />

          <Route path="apply-now" element={<ApplyNowPage />} />
          <Route path="job/:jobId/apply" element={<JobApplicationFormPage />} />
          <Route
            path="application-confirmation/:referenceNumber"
            element={<ApplicationConfirmationPage />}
          />

          <Route path="registration" element={<RegistrationPage />} />
          <Route path="register-now" element={<RegisterNowPage />} />

          <Route path="shareholders" element={<ShareholdersPage />} />
          <Route path="shares" element={<SharesPage />} />
          <Route path="shareholders-agreement" element={<ShareholdersAgreementPage />} />
          <Route path="verify/agreement/:shareholderId" element={<SignedAgreementVerifyPage />} />
          <Route path="share-purchase" element={<SharePurchasePortal />} />
          <Route 
            path="shareholder-confirmation/:referenceNumber" 
            element={<ShareholderConfirmationPage />} 
          />

          <Route path="qr-scanner" element={<QRScannerPage />} />
          <Route path="projects" element={<ProjectsPage />} />
          <Route path="contact" element={<ContactUsPage />} />
          <Route path="student/progress" element={<StudentProgressPage />} />

          {/* Audio Mixing Assistant User Routes */}
          <Route path="audio/templates" element={<MixingTemplatesLibrary />} />
          <Route path="audio/templates/:id" element={<TemplateDetailPage />} />
          <Route path="audio/assistant" element={<AIMixingAssistant />} />
          <Route path="audio/sessions" element={<MixSessions />} />
          <Route path="audio/favorites" element={<TemplateFavorites />} />
          <Route path="audio/history" element={<UsageHistory />} />

          <Route
            path="member-signup"
            element={
              <div className="p-8">
                <MemberSignupForm />
              </div>
            }
          />
        </Route>
        
        {/* Standalone Public Routes */}
        <Route path="/event/:eventId/menu-selection" element={<MenuSelectionPage />} />

        <Route path="/task-invite/:token" element={<TaskInvitePage />} />

        <Route path="/login" element={<LoginPage />} />
        <Route path="/forgot-password" element={<ForgotPasswordPage />} />
        <Route path="/otp-verification" element={<OTPVerificationScreen />} />

        <Route path="/admin/login" element={<Navigate to="/login" replace />} />
        <Route path="/applicant-login" element={<Navigate to="/login" replace />} />
        <Route path="/timesheet-login" element={<Navigate to="/login" replace />} />

        <Route
          path="/student/dashboard"
          element={
            <ProtectedRoute>
              <MainLayout>
                <StudentDashboard />
              </MainLayout>
            </ProtectedRoute>
          }
        />

        <Route
          path="/shareholder/dashboard"
          element={
            <ProtectedRoute>
              <MainLayout>
                <ShareholderDashboard />
              </MainLayout>
            </ProtectedRoute>
          }
        />

        <Route
          path="/applicant-dashboard"
          element={
            <ProtectedRoute>
              <MainLayout>
                <ApplicantDashboard />
              </MainLayout>
            </ProtectedRoute>
          }
        />

        <Route
          path="/user/tasks"
          element={
            <ProtectedRoute>
              <TaskAssigneeLayout>
                <MyTasksPage />
              </TaskAssigneeLayout>
            </ProtectedRoute>
          }
        />

        <Route
          path="/user/tasks/pending-acceptances"
          element={
            <ProtectedRoute>
              <TaskAssigneeLayout>
                <PendingAcceptancesPage />
              </TaskAssigneeLayout>
            </ProtectedRoute>
          }
        />

        <Route path="/access-denied" element={<AccessDeniedPage />} />

        <Route path="/admin" element={<Navigate to="/admin/dashboard" replace />} />

        <Route
          path="/admin"
          element={
            <ProtectedRoute requireAdmin={true}>
              <AdminLayout />
            </ProtectedRoute>
          }
        >
          <Route
            path="dashboard"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminDashboard />
              </ProtectedRoute>
            }
          />

          {/* --- AUDIO ADMIN ROUTES --- */}
          <Route path="audio/dashboard" element={<ProtectedRoute requireAdmin={true}><AudioAdminDashboard /></ProtectedRoute>} />
          <Route path="audio/instruments" element={<ProtectedRoute requireAdmin={true}><InstrumentsManagement /></ProtectedRoute>} />
          <Route path="audio/genres" element={<ProtectedRoute requireAdmin={true}><GenresManagement /></ProtectedRoute>} />
          <Route path="audio/styles" element={<ProtectedRoute requireAdmin={true}><MixStylesManagement /></ProtectedRoute>} />
          <Route path="audio/templates" element={<ProtectedRoute requireAdmin={true}><MixingTemplatesManagement /></ProtectedRoute>} />
          <Route path="audio/keywords" element={<ProtectedRoute requireAdmin={true}><InstrumentKeywordsManagement /></ProtectedRoute>} />
          <Route path="audio/recommendations" element={<ProtectedRoute requireAdmin={true}><RecommendationRules /></ProtectedRoute>} />
          <Route path="audio/access-control" element={<ProtectedRoute requireAdmin={true}><AudioAdminAccessControl /></ProtectedRoute>} />

          {/* --- DIGITAL EVENTS MODULE ROUTES --- */}
          <Route
            path="events"
            element={
              <ProtectedRoute requireAdmin={true}>
                <EventManagerPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="events/create"
            element={
              <ProtectedRoute requireAdmin={true}>
                <CreateEventPage />
              </ProtectedRoute>
            }
          />
          
          <Route path="events/analytics" element={<ProtectedRoute requireAdmin={true}><PlaceholderPage title="Event Analytics Dashboard" /></ProtectedRoute>} />
          <Route path="events/templates" element={<ProtectedRoute requireAdmin={true}><DesignTemplatesPage /></ProtectedRoute>} />
          <Route path="events/wa-templates" element={<ProtectedRoute requireAdmin={true}><WhatsAppTemplatesPage /></ProtectedRoute>} />
          <Route path="events/webhooks" element={<ProtectedRoute requireAdmin={true}><WebhookSettingsPage /></ProtectedRoute>} />
          <Route path="events/meals" element={<ProtectedRoute requireAdmin={true}><MealsListPage /></ProtectedRoute>} />
          <Route path="events/meals/create" element={<ProtectedRoute requireAdmin={true}><CreateMealPage /></ProtectedRoute>} />
          <Route path="events/meal-selections" element={<Navigate to="/admin/events/meals" replace />} />
          
          <Route
            path="invitations"
            element={
              <ProtectedRoute requireAdmin={true}>
                <InvitationListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="invitations/create"
            element={
              <ProtectedRoute requireAdmin={true}>
                <CreateInvitationPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="invitations/:id"
            element={
              <ProtectedRoute requireAdmin={true}>
                <InvitationDetailPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="check-in"
            element={
              <ProtectedRoute requireAdmin={true}>
                <QRCheckInPage />
              </ProtectedRoute>
            }
          />

          {/* Events & Highlights Management */}
          <Route
            path="events-management"
            element={
              <ProtectedRoute requireAdmin={true}>
                <EventManagementPage />
              </ProtectedRoute>
            }
          />

          {/* Task Management Admin */}
          <Route
            path="tasks/dashboard"
            element={
              <ProtectedRoute requireAdmin={true}>
                <TaskDashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="tasks"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminTaskListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="tasks/scheduled"
            element={
              <ProtectedRoute requireAdmin={true}>
                <ScheduledTasksPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="tasks/create"
            element={
              <ProtectedRoute requireAdmin={true}>
                <CreateTaskPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="tasks/settings"
            element={
              <ProtectedRoute requireAdmin={true}>
                <TaskSettingsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="tasks/my-tasks"
            element={
              <ProtectedRoute requireAdmin={true}>
                <MyTasksPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="tasks/pending-acceptances"
            element={
              <ProtectedRoute requireAdmin={true}>
                <PendingAcceptancesPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="users"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminUsersPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="roles-permissions"
            element={
              <ProtectedRoute requireSuperAdmin={true}>
                <RolesPermissionsPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="students"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminStudentsPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="courses"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminCoursesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="courses/add"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminCoursesPage defaultTab="add" />
              </ProtectedRoute>
            }
          />
          <Route
            path="registrations"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminRegistrationsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="invoices"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminInvoicesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="certificates"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminCertificatesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="progress"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminProgressPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="feedback"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminFeedbackPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="recruitment-dashboard"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminJobApplicationDashboard />
              </ProtectedRoute>
            }
          />
          <Route
            path="jobs"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminJobsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="applications"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminApplicationListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="applications/rejected"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminRejectedApplicationsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="applications/shortlisted"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminShortlistedApplicationsPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="shareholders/dashboard"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminShareHolderDashboardPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="shareholders/list"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminShareHolderListPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="shareholders/trash"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminShareHolderTrashPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="shareholders/pending-approvals"
            element={
              <ProtectedRoute requireAdmin={true}>
                <PendingShareApprovalsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="shareholders/pending-payments"
            element={
              <ProtectedRoute requireAdmin={true}>
                <PendingPaymentsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="shareholders/signed-agreements"
            element={
              <ProtectedRoute requireAdmin={true}>
                <SignedAgreementsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="shareholders/shares-list"
            element={
              <ProtectedRoute requireAdmin={true}>
                <SharesListPage />
              </ProtectedRoute>
            }
          />
          <Route
              path="shareholders"
              element={
                  <ProtectedRoute requireAdmin={true}>
                      <AdminShareHolderListPage />
                  </ProtectedRoute>
              } 
          />
          <Route
            path="shareholders/settings"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminShareSettingsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="share-listing"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminShareListingPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="members"
            element={
              <ProtectedRoute requiredPermission="manage_members">
                <AdminMembersPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="payments"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminPaymentsPage />
              </ProtectedRoute>
            }
          />
           <Route
            path="reports"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminReportsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="contact-messages"
            element={
              <ProtectedRoute requireAdmin={true}>
                <ContactMessagesPage />
              </ProtectedRoute>
            }
          />
           <Route
            path="history"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminHistoryPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="settings"
            element={<Navigate to="/admin/general-settings" replace />}
          />
          <Route
            path="system/general-settings"
            element={<Navigate to="/admin/general-settings" replace />}
          />
          <Route
            path="general-settings"
            element={
              <ProtectedRoute requireSuperAdmin={true}>
                <GeneralSystemSettingsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="backup-restore"
            element={
              <ProtectedRoute requireSuperAdmin={true}>
                <AdminBackupRestorePage />
              </ProtectedRoute>
            }
          />

          <Route path="announcements" element={<Navigate to="/admin/announcements/compose" replace />} />
          <Route
            path="announcements/compose"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AnnouncementsManagementPage mode="compose" />
              </ProtectedRoute>
            }
          />
          <Route
            path="announcements/list"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AnnouncementsManagementPage mode="list" />
              </ProtectedRoute>
            }
          />
          <Route
            path="announcements/scheduled"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AnnouncementsManagementPage mode="scheduled" />
              </ProtectedRoute>
            }
          />
          <Route
            path="announcements/templates"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AnnouncementsManagementPage mode="templates" />
              </ProtectedRoute>
            }
          />
          <Route
            path="announcements/categories"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AnnouncementCategoriesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="announcements/settings"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AnnouncementSettingsPage />
              </ProtectedRoute>
            }
          />

          <Route path="messaging/compose" element={<Navigate to="/admin/announcements/compose" replace />} />
          <Route path="messaging/listing" element={<Navigate to="/admin/announcements/list" replace />} />
          <Route path="messaging/queue" element={<Navigate to="/admin/announcements/scheduled" replace />} />
          <Route path="messaging/settings" element={<Navigate to="/admin/announcements/settings" replace />} />
          <Route path="templates" element={<Navigate to="/admin/announcements/compose" replace />} />

          <Route
            path="communication/categories"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminCommunicationCategoriesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="communication/notifications"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminNotificationComposerPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="communication/letters"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminLetterComposerPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="communication/settings"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminCommunicationSettingsPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="whatsapp-messages"
            element={
              <ProtectedRoute requireAdmin={true}>
                <WhatsAppMessageHistoryPage />
              </ProtectedRoute>
            }
          />

          <Route
            path="timesheet-report"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminTimeSheetReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="overtime-report"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminOvertimeReportPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="manage-timesheets"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminTimeSheetManagementPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="timesheet-categories"
            element={
              <ProtectedRoute requireAdmin={true}>
                <AdminTimeSheetCategoriesPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="timesheet/create-activity"
            element={
              <ProtectedRoute requireAdmin={true}>
                <ActivityManagementPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="timesheet/fill-timesheet"
            element={
              <ProtectedRoute requireAdmin={true}>
                <FillTimeSheetPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="timesheet/working-week"
            element={
              <ProtectedRoute requireAdmin={true}>
                <WorkingWeekPage />
              </ProtectedRoute>
            }
          />
          <Route
            path="timesheet/monthly-summary"
            element={
              <ProtectedRoute requireAdmin={true}>
                <MonthlyHoursSummaryPage />
              </ProtectedRoute>
            }
          />
        </Route>

        <Route path="/timesheet/create-activity" element={<Navigate to="/admin/timesheet/create-activity" replace />} />
        <Route path="/timesheet/fill-timesheet" element={<Navigate to="/admin/timesheet/fill-timesheet" replace />} />
        <Route path="/timesheet/working-week" element={<Navigate to="/admin/timesheet/working-week" replace />} />
        <Route path="/timesheet/monthly-summary" element={<Navigate to="/admin/timesheet/monthly-summary" replace />} />

        <Route path="*" element={<Navigate to="/" replace />} />
      </Routes>

      <Toaster />
      <SonnerToaster richColors position="top-right" />
    </>
  );
};

function App() {
  useEffect(() => {
    const initApp = async () => {
      try {
        // Defer queue worker — not compatible with MySQL shim (no message_queue joins)
        if (
          import.meta.env.VITE_DATA_BACKEND !== 'mysql' &&
          typeof autoStartWorker === 'function'
        ) {
          setTimeout(() => {
            try {
              autoStartWorker();
            } catch (error) {
              console.warn('Queue worker failed to auto-start:', error);
            }
          }, 5000);
        }
      } catch (error) {
        console.warn('Queue worker setup failed:', error);
      }

      // Development validation
      try {
        if (import.meta.env.DEV) {
          if (typeof validateConfig === 'function') validateConfig();
          if (typeof debugEnv === 'function') debugEnv();
        }
      } catch (e) {
        console.warn('Startup validation warnings:', e);
      }

      // Set up periodic session validation (every 10 minutes)
      const sessionValidationInterval = setInterval(async () => {
        console.log("App: Running periodic session validation...");
        
        try {
          const result = await refreshSessionIfNeeded();
          
          if (!result.success && result.cleared) {
            console.log("App: Session was cleared during validation");
            // The AuthContext will handle the session clear and show appropriate UI
          } else if (result.refreshed) {
            console.log("App: Session was refreshed successfully");
          }
        } catch (error) {
          console.error("App: Session validation error:", error);
        }
      }, SESSION_VALIDATION_INTERVAL);

      // Cleanup on unmount
      return () => {
        clearInterval(sessionValidationInterval);
      };
    };
    
    initApp();
  }, []);

  return (
    <ErrorBoundary>
      <AuthProvider>
        <TimeSheetProvider>
          <PermissionProvider>
            <WhatsAppProvider>
              <BrowserRouter>
                <AppContent />
              </BrowserRouter>
            </WhatsAppProvider>
          </PermissionProvider>
        </TimeSheetProvider>
      </AuthProvider>
    </ErrorBoundary>
  );
}

export default App;