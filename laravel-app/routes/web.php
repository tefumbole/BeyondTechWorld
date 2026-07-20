<?php

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/



Route::get('/send-whatsapp', 'WhatsAppController@send');

Route::get('/donation/payment/check', 'CartController@placeDonationAfterPayment')->name('donation.payment.check');
Route::get('/donation/payment/check/display', 'CartController@placeDonationAfterPaymentDisplay')->name('donation.payment.check.display');
Route::get('/service/payment/check', 'CartController@placeServiceAfterPayment')->name('service.payment.check');
Route::get('/service/payment/check/display', 'CartController@placeServiceAfterPaymentDisplay')->name('service.payment.check.display');
Route::get('/order/payment/check', 'CartController@placeOrderAfterPayment')->name('order.payment.check');
Route::get('/order/payment/check/display', 'CartController@placeOrderAfterPaymentDisplay')->name('order.payment.check.display');
Route::get('/booking/payment/check', 'CartController@placeBookingAfterPayment')->name('booking.payment.check');
Route::get('/booking/payment/check/display', 'CartController@placeBookingAfterPaymentDisplay')->name('booking.payment.check.display');

Route::get('/rental-agreement/{token}', 'RentalContractController@show')->name('rental.agreement');
Route::post('/rental-agreement/{token}/sign', 'RentalContractController@sign')->name('rental.agreement.sign');
Route::get('/rental-portal/{token}', 'RentalContractController@portal')->name('rental.portal');
Route::post('/rental-portal/{token}/credentials', 'RentalContractController@updateCredentials')->name('rental.portal.credentials');
Route::get('/rental/scan/{token}', 'RentalContractController@rentalScan')->name('rental.scan');

Route::get('/goods-received/{token}', 'BookingGoodsReceiptController@show')->name('goods.received.show');
Route::post('/goods-received/{token}/sign', 'BookingGoodsReceiptController@sign')->name('goods.received.sign');

Route::get('/event-contract/{token}', 'EventContractSigningController@show')->name('event.contract.sign');
Route::post('/event-contract/{token}/sign', 'EventContractSigningController@sign')->name('event.contract.sign.submit');


Route::get('/', 'BeyondController@home')->name('beyond.home');
Route::get('/about', 'BeyondController@about')->name('beyond.about');
Route::get('/services', 'BeyondController@services')->name('beyond.services');
Route::get('/projects', 'BeyondController@projects')->name('beyond.projects');
Route::get('/gallery', 'BeyondController@gallery')->name('beyond.gallery');
Route::get('/contact', 'BeyondController@contact')->name('beyond.contact');
Route::get('/events', 'PublicEventController@index')->name('beyond.events');
Route::get('/events/{slug}', 'PublicEventController@show')->name('beyond.event.detail');
Route::get('/api/public/events', 'PublicEventController@apiList');
Route::get('/api/public/events/{slug}', 'PublicEventController@apiShow');
Route::get('/trainings', 'TrainingController@trainings')->name('beyond.trainings');
Route::get('/register-now', 'TrainingController@registerNow')->name('beyond.register');
Route::post('/register-now', 'TrainingController@storeRegistration')->name('training.register');
Route::get('/registration-confirmation/{reference}', 'TrainingController@registered')->name('training.registered');
Route::redirect('/registration', '/register-now');

// Legacy upload URLs (missing /public/) → correct static path
Route::get('/uploads/applications/{file}', function ($file) {
    $safe = basename($file);
    $path = base_path('public/uploads/applications/'.$safe);
    if (! is_file($path)) {
        abort(404);
    }

    return response()->file($path);
})->where('file', '[A-Za-z0-9._-]+');

Route::get('/rentals', 'PublicRentalController@index')->name('beyond.rentals');
Route::post('/rentals', 'PublicRentalController@store')->name('beyond.rentals.store');
Route::get('/rentals/confirmation/{reference}', 'PublicRentalController@confirmation')->name('beyond.rentals.confirmation');

Route::get('/permissions', 'PublicPermissionController@index')->name('beyond.permissions');
Route::get('/permissions/name-search', 'PublicPermissionController@searchNames')->name('beyond.permissions.search');
Route::post('/permissions', 'PublicPermissionController@store')->name('beyond.permissions.store');
Route::post('/permissions/verify', 'PublicPermissionController@verify')->name('beyond.permissions.verify');
Route::post('/permissions/resend-otp', 'PublicPermissionController@resendOtp')->name('beyond.permissions.resend');
Route::get('/permissions/confirmation/{reference}', 'PublicPermissionController@confirmation')->name('beyond.permissions.confirmation');

// Student portal (training) — requires Beyond auth + OTP
Route::middleware(['beyond.auth', 'beyond.otp'])->group(function () {
    Route::get('/student/dashboard', 'StudentDashboardController@dashboard')->name('student.dashboard');
    Route::get('/student/progress', 'StudentDashboardController@progress')->name('student.progress');
    Route::post('/student/feedback', 'StudentDashboardController@submitFeedback')->name('student.feedback');
});

// Job board / Apply Now (public)
Route::get('/apply-now', 'ApplyController@index')->name('apply.index');
Route::get('/apply-now/{id}', 'ApplyController@show')->name('apply.show');
Route::post('/apply-now/{id}', 'ApplyController@store')->name('apply.store');
Route::get('/application-confirmation/{reference}', 'ApplyController@confirmation')->name('apply.confirmation');
Route::get('/application-agreement/{token}', 'ApplicationAgreementController@show')->name('apply.agreement');
Route::post('/application-agreement/{token}', 'ApplicationAgreementController@sign')->name('apply.agreement.sign');

// Applicant portal — requires Beyond auth + OTP
Route::middleware(['beyond.auth', 'beyond.otp'])->group(function () {
    Route::get('/applicant/dashboard', 'ApplicantDashboardController@dashboard')->name('applicant.dashboard');
    Route::get('/applicant/cv/{id}', 'ApplicantDashboardController@downloadCv')->name('applicant.cv');
});

// Task assignee portal — requires Beyond auth + OTP
Route::middleware(['beyond.auth', 'beyond.otp'])->group(function () {
    Route::get('/user/tasks', 'UserTaskController@index')->name('user.tasks');
    Route::get('/user/tasks/my-tasks', 'UserTaskController@index');
    Route::get('/user/tasks/pending-acceptances', 'UserTaskController@pending')->name('user.tasks.pending');
    Route::post('/user/tasks/{assignment}/accept', 'UserTaskController@accept')->name('user.tasks.accept');
    Route::post('/user/tasks/{assignment}/decline', 'UserTaskController@decline')->name('user.tasks.decline');
    Route::post('/user/tasks/{assignment}/update', 'UserTaskController@update')->name('user.tasks.update');
    Route::post('/user/tasks/{assignment}/remove', 'UserTaskController@remove')->name('user.tasks.remove');
});

// Public task invite (actions require login, enforced in controller)
Route::get('/task-invite/{token}', 'TaskInviteController@show')->name('task.invite');
Route::post('/task-invite/{token}/accept', 'TaskInviteController@accept')->name('task.invite.accept');
Route::post('/task-invite/{token}/decline', 'TaskInviteController@decline')->name('task.invite.decline');

Route::get('/shareholders', 'ShareholderController@landing')->name('shareholders.landing');
Route::post('/shareholders/accept', 'ShareholderController@acceptTerms')->name('shareholders.accept');
Route::get('/shares', 'ShareholderController@shares')->name('shareholders.shares');
Route::post('/shares', 'ShareholderController@store')->name('shareholders.store');
Route::get('/shareholder-confirmation/{reference}', 'ShareholderController@confirmation')->name('shareholders.confirmation');
Route::get('/verify/agreement/{id}', 'ShareholderController@verify')->name('shareholders.verify');
Route::redirect('/share-purchase', '/shares');

// Public payslip verification (QR / reference lookup)
Route::get('/verify/payslip/{code}', 'PayslipVerifyController@show')->name('payslip.verify');

// Staff self-service timesheet — requires Beyond auth + OTP
Route::middleware(['beyond.auth', 'beyond.otp'])->group(function () {
    Route::get('/staff/timesheet', 'StaffTimesheetController@index')->name('staff.timesheet');
    Route::post('/staff/timesheet', 'StaffTimesheetController@store')->name('staff.timesheet.store');
    Route::patch('/staff/timesheet/{id}', 'StaffTimesheetController@update')->name('staff.timesheet.update');
    Route::delete('/staff/timesheet/{id}', 'StaffTimesheetController@destroy')->name('staff.timesheet.destroy');
    Route::get('/staff/my-events', 'StaffEventTimesheetController@myEvents')->name('staff.my-events');
    Route::get('/staff/events/{assignmentId}/timesheet', 'StaffEventTimesheetController@show')->name('staff.event-timesheet');
    Route::post('/staff/events/{assignmentId}/timesheet', 'StaffEventTimesheetController@storeEntry')->name('staff.event-timesheet.entry');
    Route::post('/staff/events/{assignmentId}/timesheet/submit', 'StaffEventTimesheetController@submit')->name('staff.event-timesheet.submit');
});

// Beyond public portal auth (otp, forgot-password, profile — login registered after Auth::routes)
Route::get('/otp-verification', 'BeyondAuthController@showOtp')->name('beyond.otp');
Route::post('/otp-verification', 'BeyondAuthController@verifyOtp');
Route::post('/otp-verification/resend', 'BeyondAuthController@resendOtp');
Route::get('/forgot-password', 'BeyondAuthController@showForgotPassword')->name('beyond.forgot');
Route::post('/forgot-password', 'BeyondAuthController@requestPasswordReset');
Route::post('/forgot-password/confirm', 'BeyondAuthController@confirmPasswordReset');
Route::get('/complete-profile', 'BeyondAuthController@showCompleteProfile')->middleware('beyond.auth');
Route::post('/complete-profile', 'BeyondAuthController@completeProfile')->middleware('beyond.auth');
Route::get('/user/profile', 'BeyondAuthController@showProfile')->middleware(['beyond.auth', 'beyond.otp']);
Route::patch('/user/profile', 'BeyondAuthController@updateProfile')->middleware(['beyond.auth', 'beyond.otp']);
Route::get('/store', 'FrontendController@index')->name('frontend.home');
Route::get('/shop/logout', 'FrontendController@logout')->name('shop.logout');
Route::get('/shop/login', 'FrontendController@login')->name('shop.login');
Route::get('/shop/signup', 'FrontendController@signup')->name('shop.signup');
Route::post('/shop/signup', 'FrontendController@signupStore')->name('shop.signup');
Route::get('/shop/create', 'FrontendController@createShop')->name('create.shop');
Route::post('/shop/create', 'FrontendController@createShopStore')->name('create.shop');
Route::post('/shop/password/change', 'FrontendController@forgotPasswordCheckStore')->name('shop.password.change');
Route::get('/shop/{products}/{category?}/{brand?}', 'FrontendController@shop')->name('shop');
Route::get('/vendor/products/{id}', 'FrontendController@vendorProducts')->name('vendor.products');
Route::get('/donation/{products}', 'FrontendController@donation')->name('donation');
Route::get('/vendors/{vendors}', 'FrontendController@vendors')->name('vendors');
Route::get('/rent/{products}', 'FrontendController@rent')->name('rent');
Route::get('/service/{products}', 'FrontendController@service')->name('service');
Route::get('/donate/{id}', 'FrontendController@donate')->name('donate');
Route::get('/single-service/{id}', 'FrontendController@singleService')->name('single.service');
Route::get('/donate/detail/{id}', 'FrontendController@donateDetail')->name('donate.detail');
Route::get('/service/detail/{id}', 'FrontendController@serviceDetail')->name('service.detail');
Route::post('/donate/store', 'CartController@donateStore')->name('donate.store');
Route::post('/service/store', 'CartController@serviceStore')->name('service.store');
Route::get('/product/price', 'FrontendController@shopProductSearchByPrice')->name('product.search.price');
Route::get('/product/{id}', 'FrontendController@product')->name('product');
Route::get('/addToCart','CartController@addToCart')->name('addToCart');
Route::get('/addToRentCart','CartController@addToRentCart')->name('addToRentCart');
Route::get('/cart','CartController@cart')->name('cart');
Route::get('/rent-cart','CartController@rentCart')->name('rent.cart');
Route::post('/order','CartController@order')->name('order');
Route::post('/rent/order','CartController@rentOrder')->name('rent.order');
Route::get('/order/received/{id}','CartController@orderRceived')->name('order.received');
Route::get('/order/payment/{id}','CartController@orderPayment')->name('order.payment');
Route::get('/service/payment/{id}','CartController@servicePayment')->name('service.payment');
Route::get('/otp/resend','CartController@otpResend')->name('otp.resend');
Route::get('/otp_screen','CartController@otpScreen')->name('otp_screen');
Route::post('/otp/verify','CartController@otpVerify')->name('otp_verify');
Route::get('/cart/update','CartController@updateQuantityyNumber')->name('cart.update');
Route::get('/cart/plus','CartController@updateQuantityy')->name('cart.plus');
Route::get('/cart/minus','CartController@updateQuantityyminus')->name('cart.minus');
Route::get('/cart/delete','CartController@deleteItem')->name('rent.cart.delete');
Route::get('/rent/cart/update','CartController@updateQuantityyNumberRent')->name('rent.cart.update');
Route::get('/rent/cart/plus','CartController@updateQuantityyRent')->name('rent.cart.plus');
Route::get('/rent/cart/minus','CartController@updateQuantityyminusRent')->name('rent.cart.minus');
Route::get('/rent/cart/delete','CartController@deleteItemRent')->name('rent.cart.delete');
Route::get('/rent/cart/lims_product_search_by_duration/', 'CartController@getProductPriceByDuration')->name('frontend.booking.search_by_duration');

Route::get('/checkout','CartController@checkout')->name('checkout');
Route::get('rent//checkout','CartController@rentCheckout')->name('rent.checkout');
Route::get('frontend/product/search', 'FrontendController@productSearch')->name('frontend.product.search');
Route::get('frontend/product/rent/search', 'FrontendController@productSearchRent')->name('frontend.product.search.rent');
Route::get('frontend/product/donation/search', 'FrontendController@productSearchDonation')->name('frontend.product.search.donation');
Route::get('frontend/product/vendor/search', 'FrontendController@productSearchVendor')->name('frontend.product.search.vendor');

Route::get('/forgot/password','FrontendController@forgotPassword')->name('forgot.password');
Route::post('/forgot/password','FrontendController@forgotPasswordStore')->name('forgot.password');
Route::post('/forgot/password/verify','FrontendController@forgotPasswordCheck')->name('otp.verify.password');

Route::get('/service/variant/{id}', 'FrontendController@serviceVarient')->name('service.variant');
Route::post('/service/order', 'FrontendController@serviceOrder')->name('service.order');

Route::get('/order/invoice/{id}', 'OrderController@generateInvoice')->name('order.invoice');
Route::get('/booking/invoice/{id}', 'OrderController@bookingGenerateInvoice')->name('booking.genInvoice');


Route::get('/sale/scan/{id}', 'QRController@saleScan')->name('sale.scan');
Route::get('/quotation/scan/{id}', 'QRController@quotationScan')->name('quotation.scan');
Route::get('/letters/scan/{id}', 'QRController@letterScan')->name('letters.scan');




Auth::routes(['register' => false, 'reset' => false, 'verify' => false]);
// Admin login lives at /admin/login. Named `login` so the framework's default
// auth redirects (Authenticate middleware, admin views) resolve here — the Beyond
// portal below intentionally takes over the /login URI for public users.
Route::get('/admin/login', 'Auth\LoginController@showLoginForm')->name('login');
Route::post('/admin/login', 'Auth\LoginController@login');

// Beyond portal login (must be after Auth::routes — Laravel 6 always registers /login in auth())
Route::get('/login', 'BeyondAuthController@showLogin')->name('beyond.login');
Route::post('/login', 'BeyondAuthController@login');
Route::post('/signup', 'BeyondAuthController@register')->name('beyond.signup');
// Portal logout must NOT share POST /logout with Auth::routes (admin POS logout).
Route::post('/portal/logout', 'BeyondAuthController@logout')->name('beyond.logout');

Route::group(['middleware' => 'auth'], function() {
	Route::get('/dashboard', 'HomeController@dashboard');
});

Route::group(['middleware' => ['auth', 'active']], function() {

    // Auth::routes already registers POST /logout → LoginController@logout (overridden).
	Route::get('/otp/screen', 'HomeController@otpCheck')->name('check.otp');
	Route::post('/otp/screen/store', 'HomeController@otpCheckStore')->name('check.otp.store');
	Route::post('/otp/screen/resend', 'HomeController@otpResend')->name('check.otp.resend');
    Route::get('/admin', 'HomeController@index');

    // Site Content management (Admin/Owner) — reorder public + side menus
    Route::get('/admin/site-content', 'SiteContentController@index')->name('site-content.index');
    Route::post('/admin/site-content/landing-menu', 'SiteContentController@saveLandingMenu')->name('site-content.landing-menu');
    Route::post('/admin/site-content/side-menu', 'SiteContentController@saveSideMenu')->name('site-content.side-menu');
    Route::post('/admin/site-content/settings-menu', 'SiteContentController@saveSettingsMenu')->name('site-content.settings-menu');
    Route::post('/admin/site-content/content-tabs', 'SiteContentController@saveContentTabs')->name('site-content.content-tabs');
    Route::post('/admin/site-content/content/{page}', 'SiteContentController@saveContent')->name('site-content.content');
    Route::post('/admin/site-content/gallery/items', 'SiteContentController@storeGalleryItem')->name('site-content.gallery.store');
    Route::post('/admin/site-content/gallery/items/{id}', 'SiteContentController@updateGalleryItem')->name('site-content.gallery.update');
    Route::post('/admin/site-content/gallery/items/{id}/delete', 'SiteContentController@deleteGalleryItem')->name('site-content.gallery.delete');
    Route::post('/admin/site-content/gallery/reorder', 'SiteContentController@reorderGalleryItems')->name('site-content.gallery.reorder');

    // Task Manager (admin)
    Route::get('/admin/tasks', 'TaskManagerController@dashboard')->name('tasks.dashboard');
    Route::get('/admin/tasks/create', 'TaskManagerController@create')->name('tasks.create');
    Route::post('/admin/tasks', 'TaskManagerController@store')->name('tasks.store');
    Route::get('/admin/tasks/list', 'TaskManagerController@index')->name('tasks.index');
    Route::get('/admin/tasks/scheduled', 'TaskManagerController@scheduled')->name('tasks.scheduled');
    Route::get('/admin/tasks/reminders', 'TaskManagerController@reminders')->name('tasks.reminders');
    Route::post('/admin/tasks/reminders/{id}/delete', 'TaskManagerController@deleteReminder')->name('tasks.reminders.delete');
    Route::get('/admin/tasks/pending-acceptances', 'TaskManagerController@pendingAcceptances')->name('tasks.pending');
    Route::post('/admin/tasks/{id}/delete', 'TaskManagerController@destroy')->name('tasks.destroy');
    Route::get('/admin/tasks/settings', 'TaskManagerController@settings')->name('tasks.settings');
    Route::post('/admin/tasks/settings/categories', 'TaskManagerController@storeCategory')->name('tasks.settings.categories.store');
    Route::post('/admin/tasks/settings/categories/{id}/delete', 'TaskManagerController@destroyCategory')->name('tasks.settings.categories.destroy');
    Route::post('/admin/tasks/settings/templates', 'TaskManagerController@storeTemplate')->name('tasks.settings.templates.store');
    Route::post('/admin/tasks/settings/templates/{id}/delete', 'TaskManagerController@destroyTemplate')->name('tasks.settings.templates.destroy');
    Route::get('/admin/tasks/users/search', 'TaskManagerController@searchUsers')->name('tasks.users.search');

    // Job Board admin
    Route::get('/admin/permissions', 'StaffPermissionAdminController@index')->name('permissions.index');
    Route::get('/admin/permissions/requests', 'StaffPermissionAdminController@requests')->name('permissions.requests');
    Route::get('/admin/permissions/approved', 'StaffPermissionAdminController@approved')->name('permissions.approved');
    Route::post('/admin/permissions/{id}', 'StaffPermissionAdminController@update')->name('permissions.update');

    Route::get('/admin/jobs', 'JobBoardController@index')->name('jobs.index');
    Route::get('/admin/jobs/create', 'JobBoardController@create')->name('jobs.create');
    Route::get('/admin/jobs/create-internship', 'JobBoardController@createInternship')->name('jobs.createInternship');
    Route::post('/admin/jobs', 'JobBoardController@store')->name('jobs.store');
    Route::get('/admin/jobs/applications', 'JobBoardController@applications')->name('jobs.applications');
    Route::get('/admin/jobs/awaiting-approval', 'JobBoardController@awaiting')->name('jobs.awaiting');
    Route::get('/admin/jobs/selected', 'JobBoardController@selected')->name('jobs.selected');
    Route::get('/admin/jobs/rejected', 'JobBoardController@rejected')->name('jobs.rejected');
    Route::get('/admin/jobs/applications/{id}', 'JobBoardController@showApplication')->name('jobs.applications.show');
    Route::get('/admin/jobs/applications/{id}/document/{type}', 'JobBoardController@document')->name('jobs.applications.document')
        ->where('type', 'cv|student_id|student_id_back|letter|selfie');
    Route::post('/admin/jobs/applications/{id}', 'JobBoardController@updateApplication')->name('jobs.applications.update');
    Route::get('/admin/jobs/{id}/edit', 'JobBoardController@edit')->name('jobs.edit');
    Route::post('/admin/jobs/{id}', 'JobBoardController@update')->name('jobs.update');
    Route::post('/admin/jobs/{id}/clone', 'JobBoardController@clone')->name('jobs.clone');
    Route::post('/admin/jobs/{id}/delete', 'JobBoardController@destroy')->name('jobs.destroy');

    // WhatsApp Announcements Manager (AlphaBridge-style)
    Route::get('/admin/announcements/compose', 'AnnouncementManagerController@compose')->name('announcements.compose');
    Route::post('/admin/announcements', 'AnnouncementManagerController@store')->name('announcements.store');
    Route::get('/admin/announcements/list', 'AnnouncementManagerController@index')->name('announcements.index');
    Route::get('/admin/announcements/scheduled', 'AnnouncementManagerController@scheduled')->name('announcements.scheduled');
    Route::get('/admin/announcements/reminders', 'AnnouncementManagerController@reminders')->name('announcements.reminders');
    Route::post('/admin/announcements/reminders/{id}/delete', 'AnnouncementManagerController@deleteReminder')->name('announcements.reminders.delete');
    Route::post('/admin/announcements/{id}/delete', 'AnnouncementManagerController@destroy')->name('announcements.destroy');
    Route::get('/admin/announcements/templates', 'AnnouncementManagerController@templates')->name('announcements.templates');
    Route::post('/admin/announcements/templates', 'AnnouncementManagerController@storeTemplate')->name('announcements.templates.store');
    Route::post('/admin/announcements/templates/{id}/delete', 'AnnouncementManagerController@destroyTemplate')->name('announcements.templates.destroy');
    Route::get('/admin/announcements/categories', 'AnnouncementManagerController@categories')->name('announcements.categories');
    Route::post('/admin/announcements/categories', 'AnnouncementManagerController@storeCategory')->name('announcements.categories.store');
    Route::post('/admin/announcements/categories/{id}/delete', 'AnnouncementManagerController@destroyCategory')->name('announcements.categories.destroy');
    Route::get('/admin/announcements/settings', 'AnnouncementManagerController@settings')->name('announcements.settings');
    Route::post('/admin/announcements/settings', 'AnnouncementManagerController@updateSettings')->name('announcements.settings.update');

    // Course Manager (AlphaBridge-style)
    Route::get('/admin/courses', 'CourseManagerController@index')->name('courses.index');
    Route::get('/admin/courses/create', 'CourseManagerController@create')->name('courses.create');
    Route::post('/admin/courses', 'CourseManagerController@store')->name('courses.store');
    Route::post('/admin/courses/reorder', 'CourseManagerController@reorder')->name('courses.reorder');
    Route::get('/admin/courses/{id}/edit', 'CourseManagerController@edit')->name('courses.edit');
    Route::post('/admin/courses/{id}', 'CourseManagerController@update')->name('courses.update');
    Route::post('/admin/courses/{id}/delete', 'CourseManagerController@destroy')->name('courses.destroy');
    Route::post('/admin/courses/{id}/move', 'CourseManagerController@move')->name('courses.move');
    Route::post('/admin/courses/{id}/clone', 'CourseManagerController@cloneCourse')->name('courses.clone');
    Route::get('/admin/courses/{id}/feedback', 'CourseManagerController@courseFeedback')->name('courses.course-feedback');
    Route::get('/admin/course-registrations', 'CourseManagerController@registrations')->name('courses.registrations');
    Route::post('/admin/course-registrations/{id}', 'CourseManagerController@updateRegistration')->name('courses.registrations.update');
    Route::get('/admin/course-invoices', 'CourseManagerController@invoices')->name('courses.invoices');
    Route::get('/admin/course-certificates', 'CourseManagerController@certificates')->name('courses.certificates');
    Route::post('/admin/course-certificates/{id}/revoke', 'CourseManagerController@revokeCertificate')->name('courses.certificates.revoke');
    Route::get('/admin/course-progress', 'CourseManagerController@progress')->name('courses.progress');
    Route::post('/admin/course-progress/{id}', 'CourseManagerController@updateProgress')->name('courses.progress.update');
    Route::get('/admin/course-feedback', 'CourseManagerController@feedback')->name('courses.feedback');
    Route::post('/admin/course-feedback/{id}/delete', 'CourseManagerController@destroyFeedback')->name('courses.feedback.destroy');

    // Timesheet — Employee
    Route::get('/admin/timesheet/activities', 'TimesheetEmployeeController@activities')->name('timesheet.activities');
    Route::post('/admin/timesheet/activities', 'TimesheetEmployeeController@storeActivity')->name('timesheet.activities.store');
    Route::post('/admin/timesheet/activities/{id}', 'TimesheetEmployeeController@updateActivity')->name('timesheet.activities.update');
    Route::post('/admin/timesheet/activities/{id}/delete', 'TimesheetEmployeeController@destroyActivity')->name('timesheet.activities.destroy');
    Route::get('/admin/timesheet/fill', 'TimesheetEmployeeController@fill')->name('timesheet.fill');
    Route::post('/admin/timesheet/entries', 'TimesheetEmployeeController@storeEntry')->name('timesheet.entries.store');
    Route::post('/admin/timesheet/entries/{id}', 'TimesheetEmployeeController@updateEntry')->name('timesheet.entries.update');
    Route::post('/admin/timesheet/entries/{id}/delete', 'TimesheetEmployeeController@destroyEntry')->name('timesheet.entries.destroy');
    Route::get('/admin/timesheet/working-week', 'TimesheetEmployeeController@workingWeek')->name('timesheet.working-week');
    Route::post('/admin/timesheet/working-week', 'TimesheetEmployeeController@saveWorkingWeek')->name('timesheet.working-week.save');

    // Timesheet — Admin
    Route::get('/admin/timesheet-admin/report', 'TimesheetAdminController@report')->name('timesheet.admin.report');
    Route::get('/admin/timesheet-admin/overtime', 'TimesheetAdminController@overtime')->name('timesheet.admin.overtime');
    Route::get('/admin/timesheet-admin/manage', 'TimesheetAdminController@manage')->name('timesheet.admin.manage');
    Route::post('/admin/timesheet-admin/entries/{id}/status', 'TimesheetAdminController@updateEntryStatus')->name('timesheet.admin.entries.status');
    Route::post('/admin/timesheet-admin/entries/{id}/delete', 'TimesheetAdminController@destroyEntry')->name('timesheet.admin.entries.destroy');
    Route::get('/admin/timesheet-admin/categories', 'TimesheetAdminController@categories')->name('timesheet.admin.categories');
    Route::post('/admin/timesheet-admin/categories', 'TimesheetAdminController@storeCategory')->name('timesheet.admin.categories.store');
    Route::post('/admin/timesheet-admin/categories/{id}', 'TimesheetAdminController@updateCategory')->name('timesheet.admin.categories.update');
    Route::post('/admin/timesheet-admin/categories/{id}/delete', 'TimesheetAdminController@destroyCategory')->name('timesheet.admin.categories.destroy');

    // Events module (Phase 1)
    Route::get('/admin/events', 'EventDashboardController@index')->name('events.dashboard');
    Route::get('/admin/events/list', 'EventController@index')->name('events.index');
    Route::get('/admin/events/create', 'EventController@create')->name('events.create');
    Route::post('/admin/events', 'EventController@store')->name('events.store');
    Route::get('/admin/events/calendar', 'EventController@calendar')->name('events.calendar');
    Route::get('/admin/events/settings/categories', 'EventWorkerCategoryController@index')->name('events.settings.categories');
    Route::post('/admin/events/settings/categories', 'EventWorkerCategoryController@store')->name('events.settings.categories.store');
    Route::post('/admin/events/settings/categories/{id}', 'EventWorkerCategoryController@update')->name('events.settings.categories.update');
    Route::post('/admin/events/settings/categories/{id}/delete', 'EventWorkerCategoryController@destroy')->name('events.settings.categories.destroy');
    Route::get('/admin/events/workforce/profiles', 'EventWorkforceController@profiles')->name('events.workforce.profiles');
    Route::post('/admin/events/workforce/profiles', 'EventWorkforceController@storeProfile')->name('events.workforce.profiles.store');
    Route::get('/admin/events/workforce/search', 'EventWorkforceController@search')->name('events.workforce.search');
    Route::get('/admin/events/timesheets', 'EventTimesheetController@index')->name('events.timesheets.index');
    Route::post('/admin/events/timesheets/{id}/approve', 'EventTimesheetController@approve')->name('events.timesheets.approve');
    Route::post('/admin/events/timesheets/{id}/reject', 'EventTimesheetController@reject')->name('events.timesheets.reject');
    Route::get('/admin/events/payments', 'EventPaymentController@index')->name('events.payments.index');
    Route::post('/admin/events/payments/{paymentId}/mark-paid', 'EventPaymentController@markPaid')->name('events.payments.mark-paid');
    Route::get('/admin/events/reminders', 'EventReminderController@index')->name('events.reminders.index');
    Route::get('/admin/events/settings/contract-templates', 'EventContractTemplateController@index')->name('events.settings.contract-templates');
    Route::post('/admin/events/settings/contract-templates', 'EventContractTemplateController@store')->name('events.settings.contract-templates.store');
    Route::post('/admin/events/settings/contract-templates/{id}', 'EventContractTemplateController@update')->name('events.settings.contract-templates.update');
    Route::get('/admin/event-contracts/{contractId}/review', 'EventContractController@review')->name('events.contracts.review');
    Route::post('/admin/event-contracts/{contractId}/approve', 'EventContractController@approve')->name('events.contracts.approve');
    Route::get('/admin/event-contracts/{contractId}/preview', 'EventContractController@preview')->name('events.contracts.preview');
    Route::get('/admin/events/{id}', 'EventController@show')->name('events.show');
    Route::get('/admin/events/{id}/edit', 'EventController@edit')->name('events.edit');
    Route::put('/admin/events/{id}', 'EventController@update')->name('events.update');
    Route::delete('/admin/events/{id}', 'EventController@destroy')->name('events.destroy');
    Route::post('/admin/events/{id}/publication', 'EventPublicationController@update')->name('events.publication.update');
    Route::post('/admin/events/{id}/publish', 'EventPublicationController@publish')->name('events.publish');
    Route::post('/admin/events/{id}/unpublish', 'EventPublicationController@unpublish')->name('events.unpublish');
    Route::post('/admin/events/{id}/workforce/assign', 'EventWorkforceController@assign')->name('events.workforce.assign');
    Route::post('/admin/events/{id}/workforce/{assignmentId}/remove', 'EventWorkforceController@removeAssignment')->name('events.workforce.remove');
    Route::post('/admin/events/{id}/labour-budget', 'EventWorkforceController@saveBudget')->name('events.labour-budget.save');
    Route::post('/admin/events/{id}/contracts/generate', 'EventContractController@generate')->name('events.contracts.generate');
    Route::post('/admin/events/{id}/contracts/{contractId}/send', 'EventContractController@send')->name('events.contracts.send');
    Route::post('/admin/events/{id}/reminders', 'EventReminderController@storeForEvent')->name('events.reminders.store');
    Route::delete('/admin/events/reminders/{id}', 'EventReminderController@destroy')->name('events.reminders.destroy');
    Route::post('/admin/events/{id}/payments', 'EventPaymentController@createForAssignment')->name('events.payments.create');

	Route::get('/wp', 'HomeController@whatsapp');
	Route::get('/mmt', 'HomeController@mobileMoneyToken');
	Route::get('/mmr', 'HomeController@mobileMoneyRequest');
	Route::get('/mms', 'HomeController@mobileMoneyStatus');
	Route::get('/dashboard-filter/{start_date}/{end_date}', 'HomeController@dashboardFilter');
	Route::get('check-batch-availability/{product_id}/{batch_no}/{warehouse_id}', 'ProductController@checkBatchAvailability');

	Route::get('language_switch/{locale}', 'LanguageController@switchLanguage');

	Route::get('role/permission/{id}', 'RoleController@permission')->name('role.permission');
	Route::post('role/set_permission', 'RoleController@setPermission')->name('role.setPermission');
	Route::resource('role', 'RoleController');

	Route::post('importunit', 'UnitController@importUnit')->name('unit.import');
	Route::post('unit/deletebyselection', 'UnitController@deleteBySelection');
	Route::get('unit/lims_unit_search', 'UnitController@limsUnitSearch')->name('unit.search');
	Route::resource('unit', 'UnitController');

	Route::post('category/import', 'CategoryController@import')->name('category.import');
	Route::post('category/deletebyselection', 'CategoryController@deleteBySelection');
	Route::post('category/category-data', 'CategoryController@categoryData');
	Route::resource('category', 'CategoryController');

	Route::post('importbrand', 'BrandController@importBrand')->name('brand.import');
	Route::post('brand/deletebyselection', 'BrandController@deleteBySelection');
	Route::get('brand/lims_brand_search', 'BrandController@limsBrandSearch')->name('brand.search');
	Route::resource('brand', 'BrandController');

	Route::post('importsupplier', 'SupplierController@importSupplier')->name('supplier.import');
	Route::post('supplier/deletebyselection', 'SupplierController@deleteBySelection');
	Route::get('supplier/lims_supplier_search', 'SupplierController@limsSupplierSearch')->name('supplier.search');
	Route::resource('supplier', 'SupplierController');

	Route::post('importwarehouse', 'WarehouseController@importWarehouse')->name('warehouse.import');
	Route::post('warehouse/deletebyselection', 'WarehouseController@deleteBySelection');
	Route::get('warehouse/lims_warehouse_search', 'WarehouseController@limsWarehouseSearch')->name('warehouse.search');
	Route::resource('warehouse', 'WarehouseController');

	Route::post('importtax', 'TaxController@importTax')->name('tax.import');
	Route::post('tax/deletebyselection', 'TaxController@deleteBySelection');
	Route::get('tax/lims_tax_search', 'TaxController@limsTaxSearch')->name('tax.search');
	Route::resource('tax', 'TaxController');

	//Route::get('products/getbarcode', 'ProductController@getBarcode');
	Route::post('products/product-data', 'ProductController@productData');
	Route::post('products/product-data/vendor', 'ProductController@productDataVendor');
	Route::get('products/gencode', 'ProductController@generateCode');
	Route::get('products/search', 'ProductController@search');
	Route::get('products/saleunit/{id}', 'ProductController@saleUnit');
	Route::get('products/getdata/{id}', 'ProductController@getData');
	Route::get('products/product_warehouse/{id}', 'ProductController@productWarehouseData');
	Route::post('importproduct', 'ProductController@importProduct')->name('product.import');
	Route::post('exportproduct', 'ProductController@exportProduct')->name('product.export');
	Route::get('products/print_barcode','ProductController@printBarcode')->name('product.printBarcode');

	Route::get('products/lims_product_search', 'ProductController@limsProductSearch')->name('product.search');
	Route::post('products/deletebyselection', 'ProductController@deleteBySelection');
    Route::get('/editbyselection/warehouse/products', 'ProductController@warehouseProducts')->name('edit.by.selection.warehouse.products');
	Route::get('products/editbyselection', 'ProductController@editBySelection')->name('product.edit.by.selection');
    Route::get('products/editbyselection/page', 'ProductController@editBySelectionPage')->name('product.edit.by.selection.page');
	Route::post('products/updatebyselection', 'ProductController@updateBySelection')->name('product.update.by.selection');
	Route::post('products/update', 'ProductController@updateProduct');
	Route::get('products/store/model', 'SaleController@storeModel')->name('product.store.model');
	Route::resource('products', 'ProductController');

	Route::post('importcustomer_group', 'CustomerGroupController@importCustomerGroup')->name('customer_group.import');
	Route::post('customer_group/deletebyselection', 'CustomerGroupController@deleteBySelection');
	Route::get('customer_group/lims_customer_group_search', 'CustomerGroupController@limsCustomerGroupSearch')->name('customer_group.search');
	Route::resource('customer_group', 'CustomerGroupController');

    Route::get('customer/payment_check', 'CustomerController@CustomerPayemntCheck')->name('customer.payment_check');
	Route::post('importcustomer', 'CustomerController@importCustomer')->name('customer.import');
    Route::get('people/transfer', 'PeopleTransferController@index')->name('people.transfer');
    Route::get('people/export/customers', 'PeopleTransferController@exportCustomers')->name('people.export.customers');
    Route::get('people/export/users', 'PeopleTransferController@exportUsers')->name('people.export.users');
    Route::get('people/sample/customers', 'PeopleTransferController@sampleCustomers')->name('people.sample.customers');
    Route::get('people/sample/users', 'PeopleTransferController@sampleUsers')->name('people.sample.users');
    Route::post('people/import/customers', 'PeopleTransferController@importCustomers')->name('people.import.customers');
    Route::post('people/import/users', 'PeopleTransferController@importUsers')->name('people.import.users');
	Route::get('customer/getDeposit/{id}', 'CustomerController@getDeposit');
	Route::post('customer/add_deposit', 'CustomerController@addDeposit')->name('customer.addDeposit');
	Route::post('customer/update_deposit', 'CustomerController@updateDeposit')->name('customer.updateDeposit');
	Route::post('customer/deleteDeposit', 'CustomerController@deleteDeposit')->name('customer.deleteDeposit');
	Route::post('customer/deletebyselection', 'CustomerController@deleteBySelection');
	Route::get('customer/lims_customer_search', 'CustomerController@limsCustomerSearch')->name('customer.search');
    Route::post('/customer/inline-update', 'CustomerController@inlineUpdate')->name('customer.inlineUpdate');
	Route::resource('customer', 'CustomerController');
    Route::get('/customer/gen_payment_invoice/{id}', 'CustomerController@genInvoice')->name('customer.gen_payment_invoice');
    Route::get('/customer_group/gen_payment_invoice/{id}', 'CustomerGroupController@genInvoice')->name('customer_group.gen_payment_invoice');
    Route::get('customer_group/customers/{id}', 'CustomerController@CustomerGroupCustomers')->name('customer_group.customers');
    Route::get('customer_group/deposits/{id}', 'CustomerGroupController@Deposits')->name('customer_group.deposits');
    Route::get('customer_group/payments/{id}', 'CustomerGroupController@Payments')->name('customer_group.payments');
    Route::post('customer_group/add_deposit', 'CustomerGroupController@addDeposit')->name('customer_group.addDeposit');

	Route::post('importbiller', 'BillerController@importBiller')->name('biller.import');
	Route::post('biller/deletebyselection', 'BillerController@deleteBySelection');
	Route::get('biller/lims_biller_search', 'BillerController@limsBillerSearch')->name('biller.search');
	Route::resource('biller', 'BillerController');

    Route::get('/customer/payments/{id}', 'PaymentController@AwaitingPayments')->name('customer.awaiting.payments');
    Route::get('sales/payment_check', 'SaleController@addPaymentMTN')->name('sale.payment_check');
    Route::get('/pos/payment_check', 'SaleController@addPaymentMTNPOS')->name('pos.payment_check');
	Route::get('sales/category/associate', 'SaleController@addCategoryIdInSale');
	Route::post('sales/sale-data', 'SaleController@saleData');
	Route::post('sales/sendmail', 'SaleController@sendMail')->name('sale.sendmail');
	Route::get('sales/sale_by_csv', 'SaleController@saleByCsv');
	Route::get('sales/product_sale/{id}','SaleController@productSaleData');
	Route::post('importsale', 'SaleController@importSale')->name('sale.import');
	Route::get('pos', 'SaleController@posSale')->name('sale.pos');
	Route::get('sales/lims_sale_search', 'SaleController@limsSaleSearch')->name('sale.search');
	Route::get('sales/lims_product_search', 'SaleController@limsProductSearch')->name('product_sale.search');
	Route::get('sales/getcustomergroup/{id}', 'SaleController@getCustomerGroup')->name('sale.getcustomergroup');
	Route::get('sales/getproduct/{id}', 'SaleController@getProduct')->name('sale.getproduct');
	Route::get('sales/searchAllProducts', 'SaleController@searchAllProducts')->name('sale.search.all.products');
	Route::get('sales/searchQuickProducts', 'SaleController@searchQuickProducts')->name('sale.search.quick.products');
	Route::get('sales/get-batch-products/{id}', 'SaleController@getBatchProduct')->name('sale.getBatchProducts');
	Route::get('sales/getproduct/{category_id}/{brand_id}', 'SaleController@getProductByFilter');
	Route::get('sales/getfeatured', 'SaleController@getFeatured');
	Route::get('sales/get_gift_card', 'SaleController@getGiftCard');
	Route::get('sales/paypalSuccess', 'SaleController@paypalSuccess');
	Route::get('sales/paypalPaymentSuccess/{id}', 'SaleController@paypalPaymentSuccess');
	Route::get('sales/gen_invoice/{id}', 'SaleController@genInvoice')->name('sale.invoice');
	Route::post('sales/add_payment', 'SaleController@addPayment')->name('sale.add-payment');
	Route::get('sales/getpayment/{id}', 'SaleController@getPayment')->name('sale.get-payment');
	Route::post('sales/updatepayment', 'SaleController@updatePayment')->name('sale.update-payment');
	Route::post('sales/deletepayment', 'SaleController@deletePayment')->name('sale.delete-payment');
	Route::get('sales/{id}/create', 'SaleController@createSale');
	Route::post('sales/deletebyselection', 'SaleController@deleteBySelection');
	Route::get('sales/print-last-reciept', 'SaleController@printLastReciept')->name('sales.printLastReciept');
	Route::get('sales/today-sale', 'SaleController@todaySale');
	Route::get('sales/today-profit/{warehouse_id}', 'SaleController@todayProfit');
	Route::resource('sales', 'SaleController');
    Route::post('sales/sendwhatsapp', 'SaleController@sendWhatsapp')->name('sale.sendwhatsapp');

	Route::get('delivery', 'DeliveryController@index')->name('delivery.index');
	Route::get('delivery/product_delivery/{id}','DeliveryController@productDeliveryData');
	Route::get('delivery/create/{id}', 'DeliveryController@create');
	Route::post('delivery/store', 'DeliveryController@store')->name('delivery.store');
	Route::post('delivery/sendmail', 'DeliveryController@sendMail')->name('delivery.sendMail');
	Route::get('delivery/{id}/edit', 'DeliveryController@edit');
	Route::post('delivery/update', 'DeliveryController@update')->name('delivery.update');
	Route::post('delivery/deletebyselection', 'DeliveryController@deleteBySelection');
	Route::post('delivery/delete/{id}', 'DeliveryController@delete')->name('delivery.delete');

	Route::get('quotations/product_quotation/{id}','QuotationController@productQuotationData');
	Route::get('quotations/lims_product_search', 'QuotationController@limsProductSearch')->name('product_quotation.search');
	Route::get('quotations/getcustomergroup/{id}', 'QuotationController@getCustomerGroup')->name('quotation.getcustomergroup');
	Route::get('quotations/getproduct/{id}', 'QuotationController@getProduct')->name('quotation.getproduct');
	Route::get('quotations/{id}/create_sale', 'QuotationController@createSale')->name('quotation.create_sale');
	Route::get('quotations/{id}/create_purchase', 'QuotationController@createPurchase')->name('quotation.create_purchase');
	Route::post('quotations/sendmail', 'QuotationController@sendMail')->name('quotation.sendmail');
	Route::post('quotations/sendwhatsapp', 'QuotationController@sendWhatsapp')->name('quotation.sendwhatsapp');
	Route::post('quotations/deletebyselection', 'QuotationController@deleteBySelection');
	Route::resource('quotations', 'QuotationController');

	Route::post('purchases/purchase-data', 'PurchaseController@purchaseData')->name('purchases.data');
	Route::get('purchases/product_purchase/{id}','PurchaseController@productPurchaseData');
	Route::get('purchases/lims_product_search', 'PurchaseController@limsProductSearch')->name('product_purchase.search');
	Route::post('purchases/add_payment', 'PurchaseController@addPayment')->name('purchase.add-payment');
	Route::get('purchases/getpayment/{id}', 'PurchaseController@getPayment')->name('purchase.get-payment');
	Route::post('purchases/updatepayment', 'PurchaseController@updatePayment')->name('purchase.update-payment');
	Route::post('purchases/deletepayment', 'PurchaseController@deletePayment')->name('purchase.delete-payment');
	Route::get('purchases/purchase_by_csv', 'PurchaseController@purchaseByCsv');
	Route::post('importpurchase', 'PurchaseController@importPurchase')->name('purchase.import');
	Route::post('purchases/deletebyselection', 'PurchaseController@deleteBySelection');
	Route::resource('purchases', 'PurchaseController');

	Route::get('transfers/product_transfer/{id}','TransferController@productTransferData');
	Route::get('transfers/transfer_by_csv', 'TransferController@transferByCsv');
	Route::post('importtransfer', 'TransferController@importTransfer')->name('transfer.import');
	Route::get('transfers/getproduct/{id}', 'TransferController@getProduct')->name('transfer.getproduct');
	Route::get('transfers/lims_product_search', 'TransferController@limsProductSearch')->name('product_transfer.search');
	Route::post('transfers/deletebyselection', 'TransferController@deleteBySelection');
	Route::resource('transfers', 'TransferController');

	Route::get('qty_adjustment/getproduct/{id}', 'AdjustmentController@getProduct')->name('adjustment.getproduct');
	Route::get('qty_adjustment/lims_product_search', 'AdjustmentController@limsProductSearch')->name('product_adjustment.search');
	Route::post('qty_adjustment/deletebyselection', 'AdjustmentController@deleteBySelection');
	Route::resource('qty_adjustment', 'AdjustmentController');

	Route::get('return-sale/getcustomergroup/{id}', 'ReturnController@getCustomerGroup')->name('return-sale.getcustomergroup');
	Route::post('return-sale/sendmail', 'ReturnController@sendMail')->name('return-sale.sendmail');
	Route::get('return-sale/getproduct/{id}', 'ReturnController@getProduct')->name('return-sale.getproduct');
	Route::get('return-sale/lims_product_search', 'ReturnController@limsProductSearch')->name('product_return-sale.search');
	Route::get('return-sale/product_return/{id}','ReturnController@productReturnData');
	Route::post('return-sale/deletebyselection', 'ReturnController@deleteBySelection');
	Route::resource('return-sale', 'ReturnController');

	Route::get('return-purchase/getcustomergroup/{id}', 'ReturnPurchaseController@getCustomerGroup')->name('return-purchase.getcustomergroup');
	Route::post('return-purchase/sendmail', 'ReturnPurchaseController@sendMail')->name('return-purchase.sendmail');
	Route::get('return-purchase/getproduct/{id}', 'ReturnPurchaseController@getProduct')->name('return-purchase.getproduct');
	Route::get('return-purchase/lims_product_search', 'ReturnPurchaseController@limsProductSearch')->name('product_return-purchase.search');
	Route::get('return-purchase/product_return/{id}','ReturnPurchaseController@productReturnData');
	Route::post('return-purchase/deletebyselection', 'ReturnPurchaseController@deleteBySelection');
	Route::resource('return-purchase', 'ReturnPurchaseController');

	Route::get('report/average_sale', 'ReportController@averageSale')->name('report.averageSale');
    Route::post('report/average_sale_data', 'ReportController@averageSaleData')->name('report.average.sale');
    Route::get('report/JE', 'ReportController@JE')->name('report.JE');
    Route::post('report/JE_data', 'ReportController@JEData')->name('report.JEData');
    Route::get('report/product_quantity_alert', 'ReportController@productQuantityAlert')->name('report.qtyAlert');
	Route::get('report/warehouse_stock', 'ReportController@warehouseStock')->name('report.warehouseStock');
	Route::post('report/warehouse_stock', 'ReportController@warehouseStockById')->name('report.warehouseStock');
	Route::get('report/daily_sale/{year}/{month}', 'ReportController@dailySale');
	Route::post('report/daily_sale/{year}/{month}', 'ReportController@dailySaleByWarehouse')->name('report.dailySaleByWarehouse');
	Route::get('report/monthly_sale/{year}', 'ReportController@monthlySale');
	Route::post('report/monthly_sale/{year}', 'ReportController@monthlySaleByWarehouse')->name('report.monthlySaleByWarehouse');
	Route::get('report/daily_purchase/{year}/{month}', 'ReportController@dailyPurchase');
	Route::post('report/daily_purchase/{year}/{month}', 'ReportController@dailyPurchaseByWarehouse')->name('report.dailyPurchaseByWarehouse');
	Route::get('report/monthly_purchase/{year}', 'ReportController@monthlyPurchase');
	Route::post('report/monthly_purchase/{year}', 'ReportController@monthlyPurchaseByWarehouse')->name('report.monthlyPurchaseByWarehouse');
	Route::get('report/best_seller', 'ReportController@bestSeller');
	Route::post('report/best_seller', 'ReportController@bestSellerByWarehouse')->name('report.bestSellerByWarehouse');
	Route::post('report/profit_loss', 'ReportController@profitLoss')->name('report.profitLoss');
	Route::get('report/product_report', 'ReportController@productReport')->name('report.product');
	Route::get('report/category_report', 'ReportController@categoryReport')->name('report.category');
	Route::post('report/product_report_data', 'ReportController@productReportData');
	Route::post('report/category_report_data', 'ReportController@categoryReportData')->name('report.category.data');
	Route::post('report/purchase', 'ReportController@purchaseReport')->name('report.purchase');
	Route::post('report/sale_report', 'ReportController@saleReport')->name('report.sale');
	Route::post('report/payment_report_by_date', 'ReportController@paymentReportByDate')->name('report.paymentByDate');
	Route::post('report/warehouse_report', 'ReportController@warehouseReport')->name('report.warehouse');
	Route::post('report/user_report', 'ReportController@userReport')->name('report.user');
	Route::post('report/customer_report', 'ReportController@customerReport')->name('report.customer');
	Route::post('report/supplier', 'ReportController@supplierReport')->name('report.supplier');
	Route::post('report/due_report_by_date', 'ReportController@dueReportByDate')->name('report.dueByDate');
    Route::get('report/due_customer_data', 'ReportController@DueCustomerReport')->name('due-customer-report');

	Route::get('user/profile/{id}', 'UserController@profile')->name('user.profile');
	Route::put('user/update_profile/{id}', 'UserController@profileUpdate')->name('user.profileUpdate');
	Route::put('user/changepass/{id}', 'UserController@changePassword')->name('user.password');
	Route::get('user/genpass', 'UserController@generatePassword');
	Route::post('user/deletebyselection', 'UserController@deleteBySelection');
	Route::resource('user','UserController');

	Route::get('setting/general_setting', 'SettingController@generalSetting')->name('setting.general');
	Route::post('setting/general_setting_store', 'SettingController@generalSettingStore')->name('setting.generalStore');
	Route::get('setting/env_setting', 'SettingController@envSetting')->name('setting.env');
	Route::post('setting/env_setting_store', 'SettingController@envSettingStore')->name('setting.envStore');

	Route::get('setting/reward-point-setting', 'SettingController@rewardPointSetting')->name('setting.rewardPoint');
	Route::post('setting/reward-point-setting_store', 'SettingController@rewardPointSettingStore')->name('setting.rewardPointStore');

	Route::get('backup', 'SettingController@backup')->name('setting.backup');
	Route::get('setting/general_setting/change-theme/{theme}', 'SettingController@changeTheme');
	Route::get('setting/mail_setting', 'SettingController@mailSetting')->name('setting.mail');
	Route::get('setting/sms_setting', 'SettingController@smsSetting')->name('setting.sms');
	Route::get('setting/createsms', 'SettingController@createSms')->name('setting.createSms');
	Route::post('setting/sendsms', 'SettingController@sendSms')->name('setting.sendSms');
	Route::get('setting/hrm_setting', 'SettingController@hrmSetting')->name('setting.hrm');
	Route::post('setting/hrm_setting_store', 'SettingController@hrmSettingStore')->name('setting.hrmStore');
	Route::post('setting/mail_setting_store', 'SettingController@mailSettingStore')->name('setting.mailStore');
	Route::post('setting/sms_setting_store', 'SettingController@smsSettingStore')->name('setting.smsStore');
	Route::get('setting/pos_setting', 'SettingController@posSetting')->name('setting.pos');
	Route::post('setting/pos_setting_store', 'SettingController@posSettingStore')->name('setting.posStore');
	Route::get('setting/empty-database', 'SettingController@emptyDatabase')->name('setting.emptyDatabase');

	Route::get('expense_categories/gencode', 'ExpenseCategoryController@generateCode');
	Route::post('expense_categories/import', 'ExpenseCategoryController@import')->name('expense_category.import');
	Route::post('expense_categories/deletebyselection', 'ExpenseCategoryController@deleteBySelection');
	Route::resource('expense_categories', 'ExpenseCategoryController');

	Route::post('expenses/deletebyselection', 'ExpenseController@deleteBySelection');
	Route::resource('expenses', 'ExpenseController');
	Route::get('/expense/asset', 'ExpenseController@asset')->name('asset.expense');
	Route::get('/activity/asset', 'ExpenseController@assetActivity')->name('asset.activity');
	Route::get('/activity/repair', 'ExpenseController@assetActivityRepair')->name('asset.activity.repair');
	Route::post('assets/expense/store', 'ExpenseController@assetStore')->name('expense_asset.store');
	Route::put('assets/expense/update/{id}', 'ExpenseController@updateAsset')->name('expense_asset.update');
	Route::delete('assets/expense/delete/{id}', 'ExpenseController@destroyAsset')->name('expense_asset.destroy');
	Route::get('expense/assets/edit/{id}', 'ExpenseController@editAsset')->name('expense_asset.edit');
    Route::get('activity/assets/edit/{id}', 'ExpenseController@editAsset')->name('activity_asset.edit');
	Route::get('expense/assets/show/{id}', 'ExpenseController@showAsset')->name('expense_asset.show');
	Route::get('activity/assets/show/{id}', 'ExpenseController@showAsset')->name('activity_asset.show');
    Route::resource('activity', 'ActivityController');

	Route::get('gift_cards/gencode', 'GiftCardController@generateCode');
	Route::post('gift_cards/recharge/{id}', 'GiftCardController@recharge')->name('gift_cards.recharge');
	Route::post('gift_cards/deletebyselection', 'GiftCardController@deleteBySelection');
	Route::resource('gift_cards', 'GiftCardController');

	Route::get('coupons/gencode', 'CouponController@generateCode');
	Route::post('coupons/deletebyselection', 'CouponController@deleteBySelection');
	Route::resource('coupons', 'CouponController');
	//accounting routes
	Route::get('accounts/make-default/{id}', 'AccountsController@makeDefault');
	Route::get('accounts/balancesheet', 'AccountsController@balanceSheet')->name('accounts.balancesheet');
	Route::post('accounts/account-statement', 'AccountsController@accountStatement')->name('accounts.statement');
	Route::resource('accounts', 'AccountsController');
	Route::resource('money-transfers', 'MoneyTransferController');
    Route::post('accounts/import', 'AccountsController@import')->name('account.import');
	//HRM routes
	Route::post('departments/deletebyselection', 'DepartmentController@deleteBySelection');
	Route::resource('departments', 'DepartmentController');
    Route::post('departments/import', 'DepartmentController@import')->name('departments.import');

	Route::post('employees/deletebyselection', 'EmployeeController@deleteBySelection');
	Route::post('employees/store/letter', 'EmployeeController@storeLetter')->name('employees.store.letter');
	Route::resource('employees', 'EmployeeController');

	Route::post('payroll/deletebyselection', 'PayrollController@deleteBySelection');
	Route::resource('payroll', 'PayrollController');

	Route::post('attendance/deletebyselection', 'AttendanceController@deleteBySelection');
	Route::resource('attendance', 'AttendanceController');

	Route::resource('stock-count', 'StockCountController');
	Route::post('stock-count/finalize', 'StockCountController@finalize')->name('stock-count.finalize');
	Route::get('stock-count/stockdif/{id}', 'StockCountController@stockDif');
	Route::get('stock-count/{id}/qty_adjustment', 'StockCountController@qtyAdjustment')->name('stock-count.adjustment');

	Route::post('holidays/deletebyselection', 'HolidayController@deleteBySelection');
	Route::get('approve-holiday/{id}', 'HolidayController@approveHoliday')->name('approveHoliday');
	Route::get('holidays/my-holiday/{year}/{month}', 'HolidayController@myHoliday')->name('myHoliday');
	Route::resource('holidays', 'HolidayController');

	Route::get('cash-register', 'CashRegisterController@index')->name('cashRegister.index');
	Route::get('cash-register/check-availability/{warehouse_id}', 'CashRegisterController@checkAvailability')->name('cashRegister.checkAvailability');
	Route::post('cash-register/store', 'CashRegisterController@store')->name('cashRegister.store');
	Route::get('cash-register/getDetails/{id}', 'CashRegisterController@getDetails');
	Route::get('cash-register/showDetails/{warehouse_id}', 'CashRegisterController@showDetails');
	Route::post('cash-register/close', 'CashRegisterController@close')->name('cashRegister.close');

	Route::post('notifications/store', 'NotificationController@store')->name('notifications.store');
	Route::get('notifications/mark-as-read', 'NotificationController@markAsRead');

	Route::resource('currency', 'CurrencyController');

	Route::get('/home', 'HomeController@index')->name('home');
	Route::get('my-transactions/{year}/{month}', 'HomeController@myTransaction');


    Route::resource('region', 'RegionController');
    Route::resource('station', 'StationController');
    Route::resource('donor', 'DonorController');
    Route::resource('assetCategory', 'AssetCategoryController');
    Route::resource('asset', 'AssetController');

    Route::get('/assets/dispose/form/{id}', 'AssetController@destroyAsset')->name('asset.dispose.form');
    Route::get('/assets/dispose/form/all', 'AssetController@destroyAssetAll')->name('asset.dispose.form.all');
    Route::post('/assets/dispose/update', 'AssetController@destroyAssetUpdate')->name('asset.dispose.update');
    Route::post('/assets/dispose', 'AssetController@destroyAssetData')->name('asset.dispose');
    Route::get('/assets/dispose/edit/{id}', 'AssetController@destroyAssetEdit')->name('asset.dispose.edit');
    Route::get('/assets/dispose/list', 'AssetController@destroyAssetList')->name('asset.dispose.list');

    Route::get('/assets/transfer/list', 'AssetController@transferAssetList')->name('asset.transfer.list');
    Route::get('/assets/transfer/search/{id}', 'AssetController@transferAssetSearch')->name('asset.transfer.search');
    Route::get('/assets/transfer/form/single/{id}', 'AssetController@transferAsset')->name('asset.transfer.form');
    Route::get('/assets/transfer/letter/{id}', 'AssetController@transferLetterAsset')->name('asset.transfer.letter');
    Route::get('/assets/transfer/form/all', 'AssetController@transferAssetAll')->name('asset.transfer.all');
    Route::get('/assets/transfer/edit/{id}', 'AssetController@transferAssetEdit')->name('asset.transfer.edit');
    Route::post('/assets/transfer/update', 'AssetController@transferAssetUpdate')->name('asset.transfer.update');
    Route::post('/assets/transfer', 'AssetController@transferAssetData')->name('asset.transfer');

    Route::get('/assets/sale/list', 'AssetController@saleAssetList')->name('asset.sale.list');
    Route::get('/assets/sale/search/{id}', 'AssetController@saleAssetSearch')->name('asset.sale.search');
    Route::get('/assets/sale/form/single/{id}', 'AssetController@saleAsset')->name('asset.sale.form');
    Route::get('/assets/sale/letter/{id}', 'AssetController@saleLetterAsset')->name('asset.sale.letter');
    Route::get('/assets/sale/form/all', 'AssetController@saleAssetAll')->name('asset.sale.all');
    Route::post('/assets/sale', 'AssetController@saleAssetData')->name('asset.sale');
    Route::post('/assets/sale/update', 'AssetController@saleAssetDataUpdate')->name('asset.sale.update');
    Route::get('/assets/sale/show/{id}', 'AssetController@saleAssetShow')->name('asset.sale.show');
    Route::get('/assets/sale/edit/{id}', 'AssetController@saleAssetEdit')->name('asset.sale.edit');

    Route::get('/asset/images/delete/{id}', 'AssetController@AssetImageDelete')->name('asset.image.delete');
    Route::get('/asset/department/{id}', 'AssetController@DepartmentSearch')->name('asset.department.search');
    Route::match(['get', 'post'], 'asset/category/dashboard', 'AssetController@Dashboard')->name('asset.dashboard');
    Route::get('asset.dashboard.category/{id}', 'AssetController@DashboardCategory')->name('asset.dashboard.category');
    Route::get('asset/report/dashboard', 'AssetController@Report')->name('asset.report.dashboard');
    Route::get('asset/report/category', 'AssetController@Category')->name('asset.report.category');
    Route::get('asset/report/department', 'AssetController@Department')->name('asset.report.department');
    Route::get('asset/report/donor', 'AssetController@Donor')->name('asset.report.donor');
    Route::get('asset/report/region', 'AssetController@Region')->name('asset.report.region');
    Route::get('asset/report/station', 'AssetController@Station')->name('asset.report.station');
    Route::post('asset/report/category', 'AssetController@CategoryData')->name('asset.report.category');
    Route::post('asset/report/department', 'AssetController@DepartmentData')->name('asset.report.department');
    Route::post('asset/report/donor', 'AssetController@DonorData')->name('asset.report.donor');
    Route::post('asset/report/region', 'AssetController@RegionData')->name('asset.report.region');
    Route::post('asset/report/station', 'AssetController@StationData')->name('asset.report.station');
    Route::get('asset/report/expense', 'AssetController@expenseReport')->name('asset.report.expense');
    Route::post('asset/report/expense', 'AssetController@ExpenseData')->name('asset.report.expense');
    Route::get('asset/report/photocopy', 'AssetController@photocopy')->name('asset.report.photocopy');
    Route::post('asset/report/photocopy', 'AssetController@photocopyData')->name('asset.report.photocopy');
    Route::get('asset/report/repair', 'AssetController@repair')->name('asset.report.repair');
    Route::post('asset/report/repair', 'AssetController@repairData')->name('asset.report.repair');
    Route::get('asset/report/general', 'AssetController@general')->name('asset.report.general');
    Route::post('asset/report/general', 'AssetController@generalData')->name('asset.report.general');
    Route::get('asset/report/dispose', 'AssetController@dispose')->name('asset.report.dispose');
    Route::post('asset/report/dispose', 'AssetController@disposeData')->name('asset.report.dispose');
    Route::get('asset/report/transfer', 'AssetController@transfer')->name('asset.report.transfer');
    Route::post('asset/report/transfer', 'AssetController@transferData')->name('asset.report.transfer');

    Route::get('asset/report/category/new', 'AssetController@CategoryNew')->name('asset.report.category.new');
    Route::post('asset/report/category/new', 'AssetController@CategoryDataNew')->name('asset.report.category.new');

    Route::get('/bookings/create', 'BookingController@create')->name('booking.create');
    Route::get('/bookings/clone/{id}', 'BookingController@cloneBooking')->name('booking.clone');
    Route::post('/bookings/store', 'BookingController@store')->name('booking.store');
    Route::post('/bookings/quick-customer', 'BookingController@quickStoreCustomer')->name('booking.quick-customer');
    Route::post('/bookings/quick-product', 'BookingController@quickStoreProduct')->name('booking.quick-product');
    Route::DELETE('/bookings/destroy/{id}', 'BookingController@destroy')->name('booking.destroy');
    Route::get('/bookings/{id}/edit', 'BookingController@edit')->name('booking.edit');
    Route::PUT('/bookings/update/{id}', 'BookingController@update')->name('booking.update');

    Route::get('bookings/category/associate', 'BookingController@addCategoryIdInSale');
    Route::get('bookings/index', 'BookingController@index')->name('booking.index');
    Route::get('bookings/reminders', 'BookingReminderController@index')->name('booking.reminders');
    Route::post('bookings/reminders', 'BookingReminderController@store')->name('booking.reminders.store');
    Route::delete('bookings/reminders/{id}', 'BookingReminderController@destroy')->name('booking.reminders.destroy');
    Route::get('bookings/awaiting-signature', 'RentalContractController@awaitingIndex')->name('booking.awaiting-signature');
    Route::get('bookings/pending-review', 'RentalContractController@pendingReviewIndex')->name('booking.pending-review');
    Route::get('bookings/signed-contracts', 'RentalContractController@signedIndex')->name('booking.signed-contracts');
    Route::get('bookings/contract/{id}/id-card', 'RentalContractController@viewIdCard')->name('booking.contract.id-card');
    Route::get('bookings/contract/{id}/view', 'RentalContractController@viewContract')->name('booking.contract.view');
    Route::get('bookings/contract/{id}/review', 'RentalContractController@reviewShow')->name('booking.contract.review');
    Route::get('bookings/contract/{id}/approve', 'RentalContractController@approveReviewRedirect')->name('booking.contract.approve.redirect');
    Route::post('bookings/contract/{id}/approve', 'RentalContractController@approveContract')->name('booking.contract.approve');
    Route::post('bookings/contract/{id}/resend', 'RentalContractController@resend')->name('booking.contract.resend');
    Route::delete('bookings/contract/{id}', 'RentalContractController@destroyContract')->name('booking.contract.destroy');
    Route::get('bookings/goods-received', 'BookingGoodsReceiptController@index')->name('booking.goods-received');
    Route::get('bookings/goods-received/generate/{bookingId}', 'BookingGoodsReceiptController@generate')->name('booking.goods-received.generate');
    Route::get('bookings/goods-received/{id}/delivery-note', 'BookingGoodsReceiptController@deliveryNote')->name('booking.goods-received.delivery-note');
    Route::post('bookings/goods-received/{id}/send-signature', 'BookingGoodsReceiptController@sendSignature')->name('booking.goods-received.send-signature');
    Route::post('bookings/goods-received/{id}/resend', 'BookingGoodsReceiptController@resend')->name('booking.goods-received.resend');
    Route::get('bookings/goods-received/{id}/signed-pdf', 'BookingGoodsReceiptController@signedPdf')->name('booking.goods-received.signed-pdf');
    Route::get('online/bookings/index', 'BookingController@onlineIndex')->name('online.booking.index');
    Route::get('bookings/requests', 'BookingController@bookingRequests')->name('booking.requests');
    Route::get('bookings/gen_invoice/{id}', 'BookingController@genInvoice')->name('booking.invoice');
    Route::get('bookings/returns/{id}', 'BookingController@return')->name('booking.return');
    Route::post('/bookings/return/data/{id}', 'BookingController@returnData')->name('booking.return.data');
    Route::get('bookings/products', 'BookingController@bookedproducts')->name('booking.product');
    Route::post('/bookings/product/report', 'BookingController@bookedproductsReport')->name('booking.product.report');

    Route::post('bookings/add_payment', 'BookingController@addPayment')->name('booking.add-payment');
    Route::get('/bookings/getpayment/{id}', 'BookingController@getPayment')->name('booking.get-payment');
    Route::post('/bookings/updatepayment', 'BookingController@updatePayment')->name('booking.update-payment');
    Route::post('/bookings/deletepayment', 'BookingController@deletePayment')->name('booking.delete-payment');
    Route::post('bookings/sale-data', 'BookingController@saleData');
    Route::post('bookings/sale-data-online', 'BookingController@saleDataOnline');
    Route::post('bookings/sendmail', 'BookingController@sendMail')->name('booking.sendmail');
    Route::get('bookings/product_sale/{id}','BookingController@productSaleData');
    Route::get('bookings/lims_sale_search', 'BookingController@limsSaleSearch')->name('sale.search');
    Route::get('bookings/lims_product_search', 'BookingController@limsProductSearch')->name('product_sale.search');
    Route::get('bookings/getcustomergroup/{id}', 'BookingController@getCustomerGroup')->name('sale.getcustomergroup');
    Route::get('bookings/getproduct/{id}', 'BookingController@getProduct')->name('sale.getproduct');
    Route::get('bookings/get-batch-products/{id}', 'BookingController@getBatchProduct')->name('sale.getBatchProducts');
    Route::get('bookings/getproduct/{category_id}/{brand_id}', 'BookingController@getProductByFilter');
    Route::get('bookings/lims_product_search_by_duration/', 'BookingController@getProductPriceByDuration')->name('booking.search_by_duration');
    Route::get('bookings/lims_product_search_qty_by_duration/', 'BookingController@getProductQtyByDuration')->name('booking.search_qty_by_duration');
    Route::post('bookings/sendwhatsapp', 'BookingController@sendWhatsapp')->name('booking.sendwhatsapp');

    Route::get('report/daily_booking/{year}/{month}', 'ReportController@dailyBooking');
    Route::post('report/daily_booking/{year}/{month}', 'ReportController@dailyBookingByWarehouse')->name('report.dailyBookingByWarehouse');



    Route::get('/letter/attachment/delete/{id}', 'LetterController@letterAttachmentDelete')->name('letter.attachment.delete');
    Route::get('/letter/attachment/delete/first/{id}', 'LetterController@letterAttachmentDeleteFirst')->name('letter.attachment.delete.first');
    Route::get('/letters/next/{id}', 'LetterController@next')->name('letter.next');
    Route::get('/letters/prev/{id}', 'LetterController@prev')->name('letter.prev');
    Route::get('/letters/create', 'LetterController@create')->name('letter.create');
    Route::get('/letters/clone/{id}', 'LetterController@cloneLetter')->name('letter.clone');
    Route::get('/letters/index', 'LetterController@index')->name('letter.index');
    Route::get('/letters/all', 'LetterController@all')->name('letter.all');
    Route::get('/letters/approved', 'LetterController@approved')->name('letter.index.approved');
    Route::get('/letters/rejected', 'LetterController@rejected')->name('letter.index.rejected');
    Route::get('/letters/edited', 'LetterController@edited')->name('letter.index.edited');
    Route::get('/letters/signed', 'LetterController@signed')->name('letter.index.signed');
    Route::get('/letters/sent', 'LetterController@sent')->name('letter.index.sent');
    Route::get('/letters/sent/print', 'LetterController@sentPrint')->name('letter.index.sent.print');
    Route::get('/letters/sent/download', 'LetterController@sentDownload')->name('letter.index.sent.download');
    Route::get('/letters/show/{id}', 'LetterController@show')->name('letter.show');
    Route::post('/letters/store', 'LetterController@store')->name('letter.store');
    Route::delete('/letters/destroy/{id}', 'LetterController@destroy')->name('letter.destroy');
    Route::get('/letters/delete/{id}', 'LetterController@destroy')->name('letter.delete');
    Route::get('/letters/approve/{id}', 'LetterController@approve')->name('letter.approve');
    Route::POST('/letters/approve/store/{id}', 'LetterController@approveStore')->name('letter.approve.store');
    Route::get('/letters/reject/{id}', 'LetterController@reject')->name('letter.reject');
    Route::POST('/letters/reject/store/{id}', 'LetterController@rejectStore')->name('letter.reject.store');
    Route::get('/letters/{id}/edit', 'LetterController@edit')->name('letter.edit');
    Route::get('/letters/{id}/edit/last', 'LetterController@editLast')->name('letter.edit.last');
    Route::get('/letters/{id}/edit/ok', 'LetterController@editOk')->name('letter.edit.ok');
    Route::POST('/letters/{id}/edit/ok/store', 'LetterController@editOkStore')->name('letter.edit.ok.store');
    Route::get('/letters/{id}/sign', 'LetterController@sign')->name('letter.sign');
    Route::get('/letters/{id}/sign/send', 'LetterController@signSend')->name('letter.sign.send');
    Route::POST('/letters/{id}/sign/send/store', 'LetterController@signSendStore')->name('letter.sign.send.store');
    Route::POST('/letters/sign/store/{id}', 'LetterController@signStore')->name('letter.sign.store');
    Route::get('/letters/{id}/send', 'LetterController@send')->name('letter.send');
    Route::get('/letters/{id}/send/whatsapp', 'LetterController@sendWhatsapp')->name('letter.send.whatsapp');
    Route::get('/letters/{id}/send/mail', 'LetterController@sendEmail')->name('letter.send.mail');
    Route::get('/letters/{id}/download', 'LetterController@download')->name('letter.send.download');
    Route::get('/letters/{id}/print', 'LetterController@print')->name('letter.send.print');
    Route::POST('/letters/send/store/{id}', 'LetterController@sendStore')->name('letter.send.store');
    Route::post('/letters/update/{id}', 'LetterController@update')->name('letter.update');
    Route::post('/letters/update/last/{id}', 'LetterController@updateLast')->name('letter.update.last');
    Route::get('/letters/template/info/{id}', 'LetterController@templateInfo')->name('letter.template.info');
    Route::post('/letters/upload/image', 'App\Http\Controllers\LetterController@imageUpload')->name('letter.upload.image');


    Route::POST('/letters/multiple/ok', 'LetterController@multipleOk')->name('letter.multiple.ok');
    Route::POST('/letters/multiple/ok/store', 'LetterController@multipleOkStore')->name('letter.multiple.ok.store');
    Route::POST('/letters/multiple/approve', 'LetterController@multipleApprove')->name('letter.multiple.approve');
    Route::POST('/letters/multiple/approve/store', 'LetterController@multipleApproveStore')->name('letter.multiple.approve.store');
    Route::POST('/letters/multiple/sign', 'LetterController@multipleSign')->name('letter.multiple.sign');
    Route::POST('/letters/multiple/sign/store', 'LetterController@multipleSignStore')->name('letter.multiple.sign.store');
    Route::POST('/letters/multiple/send', 'LetterController@multipleSend')->name('letter.multiple.send');
    Route::POST('/letters/multiple/send/store', 'LetterController@multipleSendStore')->name('letter.multiple.send.store');
    Route::POST('/letters/multiple/download/store', 'LetterController@multipleDownloadStore')->name('letter.multiple.download.store');
    Route::POST('/letters/multiple/print/store', 'LetterController@multiplePrintStore')->name('letter.multiple.print.store');


    Route::get('/letters/category/create', 'LetterCategoryController@create')->name('letter.category.create');
    Route::get('/letters/category', 'LetterCategoryController@index')->name('letter.category');
    Route::post('/letters/category/store', 'LetterCategoryController@store')->name('letter.category.store');
    Route::delete('/letters/category/destroy/{id}', 'LetterCategoryController@destroy')->name('letter.category.destroy');
    Route::PUT('/letters/category/update/{id}', 'LetterCategoryController@update')->name('letter.category.update');
    Route::get('/letters/category/edit/{id}', 'LetterCategoryController@edit')->name('letter.category.edit');

    Route::get('/letters/template/index', 'LetterTemplateController@index')->name('letter.template.index');
    Route::delete('/letters/template/delete/{id}', 'LetterTemplateController@destroy')->name('letter.template.delete');
    Route::get('/letters/template/edit/{id}', 'LetterTemplateController@edit')->name('letter.template.edit');
    Route::post('/letters/template/update/{id}', 'LetterTemplateController@update')->name('letter.template.update');
    Route::get('/letters/template/show/{id}', 'LetterTemplateController@show')->name('letter.template.show');


    Route::get('/orders/index', 'OrderController@index')->name('order.index');
    Route::get('/donations/list', 'OrderController@donationList')->name('donation.list');
    Route::get('/services/list', 'OrderController@serviceList')->name('services.list');
    Route::get('/orders/show/{id}', 'OrderController@show')->name('order.show');
    Route::get('/donations/show/{id}', 'OrderController@donationShow')->name('donation.show');
    Route::get('/services/show/{id}', 'OrderController@serviceShow')->name('service.show');
    Route::get('/services/delete/{id}', 'OrderController@serviceDelete')->name('service.delete');
    Route::get('/donation/delete/{id}', 'OrderController@donationDelete')->name('donation.delete');
    Route::get('/orders/edit/{id}', 'OrderController@edit')->name('order.edit');
    Route::post('/orders/update/{id}', 'OrderController@update')->name('order.update');
    Route::post('/orders/service/update', 'OrderController@serviceUpdate')->name('service.update');
    Route::get('/orders/delete/{id}', 'OrderController@delete')->name('order.delete');
    Route::get('/orders/delete/doc/{id}', 'OrderController@deleteDoc')->name('order.delete.doc');
    Route::get('/orders/withdraw/{id}', 'OrderController@withdraw')->name('order.withdraw');


    Route::get('/shops/orders/{id}', 'OrderController@shopOrders')->name('shop.orders');

    Route::get('payment/index', 'PaymentController@index')->name('payment.index');
    Route::get('payment/desposits', 'PaymentController@Desposit')->name('deposit.index');
    Route::get('/payment/list', 'OrderController@paymentList')->name('payment.list');
    Route::get('/shops/payment/list/{id}', 'OrderController@paymentListShop')->name('shop.payments');
    Route::get('/payment/delete/{id}', 'OrderController@paymentDelete')->name('payment.delete');
    Route::get('/payment/edit/{id}', 'OrderController@paymentEdit')->name('payment.edit');
    Route::post('/payment/update/{id}', 'OrderController@paymentUpdate')->name('payment.update');

    Route::get('/shops', 'ShopController@index')->name('shop.index');
    Route::get('/shops/delete/{id}', 'ShopController@delete')->name('shop.delete');
    Route::get('/shops/products/{id}', 'ShopController@products')->name('shop.products');
    Route::get('/shops/show/{id}', 'ShopController@show')->name('shop.show');
    Route::get('/shops/edit/{id}', 'ShopController@edit')->name('shop.edit');
    Route::post('/shops/update/{id}', 'ShopController@update')->name('shop.update');


    Route::get('frontend/orders/index', 'OrderController@frontendOrderIndex')->name('frontend.order.index');
    Route::get('frontend/books/index', 'OrderController@frontendBookIndex')->name('frontend.book.index');
    Route::get('frontend/donations/index', 'OrderController@frontendDonationIndex')->name('frontend.donation.index');
    Route::get('frontend/services/index', 'OrderController@frontendServiceIndex')->name('frontend.service.index');
    Route::get('frontend/orders/track', 'OrderController@frontendOrderTrack')->name('frontend.order.track');
    Route::post('frontend/orders/track', 'OrderController@orderStatus')->name('order.status');
    Route::get('frontend/user/account', 'UserController@frontendUserAccount')->name('frontend.user.account');
    Route::post('frontend/user/account', 'UserController@frontendUserAccountUpdate')->name('frontend.user.account.update');
    Route::post('frontend/user/password', 'UserController@frontendChangePassword')->name('frontend.user.password.update');

    Route::resource('review', 'ReviewController');

    Route::get('/announcement/index', 'AnnouncementController@index')->name('announcement.index');
    Route::get('/announcement/create', 'AnnouncementController@create')->name('announcement.create');
    Route::get('/announcement/show/{id}', 'AnnouncementController@show')->name('announcement.show');
    Route::post('/announcement/store', 'AnnouncementController@store')->name('announcement.store');
    Route::get('/announcement/{announcement}/edit', 'AnnouncementController@edit')->name('announcement.edit');
    Route::put('/announcement/{announcement}', 'AnnouncementController@update')->name('announcement.update');
    Route::post('/announcement/update/{id}', 'AnnouncementController@update')->name('announcement.update');
    Route::get('/announcement/delete/{id}', 'AnnouncementController@destroy')->name('announcement.destroy');

    Route::post('/announcement/upload/image', 'AnnouncementController@imageUpload')->name('announcement.upload.image');
    Route::get('/announcement/{id}/send', 'AnnouncementController@send')->name('announcement.send');
    Route::get('/announcement/{id}/send/whatsapp', 'AnnouncementController@sendWhatsapp')->name('announcement.send.whatsapp');
    Route::get('/announcement/{id}/send/mail', 'AnnouncementController@sendEmail')->name('announcement.send.mail');
    Route::get('/announcement/{id}/download', 'AnnouncementController@download')->name('announcement.send.download');
    Route::get('/announcement/{id}/print', 'AnnouncementController@print')->name('announcement.send.print');
    Route::get('/announcement/attachment/delete/{id}', 'AnnouncementController@announcementAttachmentDelete')->name('announcement.attachment.delete');
    Route::get('/announcement/attachment/delete/first/{id}', 'AnnouncementController@announcementAttachmentDeleteFirst')->name('announcement.attachment.delete.first');

});

