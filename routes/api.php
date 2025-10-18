<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\PatientController;
use App\Http\Controllers\ProviderController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\RegisterController;
use App\Http\Controllers\PatientRegistrationController;
use App\Http\Controllers\staff_PatientListController;
use App\Http\Controllers\StaffSchedulingController;
use App\Http\Controllers\TreatmentController;
use App\Http\Controllers\PatientsController;
use App\Http\Controllers\PatientDashboardController;
use App\Http\Controllers\PatientScheduleController;
use App\Http\Controllers\DoctorTreatmentController;
use App\Http\Controllers\StaffDashboardController;
use App\Http\Controllers\View_ScheduleController;
use App\Http\Controllers\Treatments_Controller;
use App\Http\Controllers\IotController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\HCPRegisterController;
use App\Http\Controllers\ValidateEmployeeController;
use App\Http\Controllers\OTPController;
use App\Http\Controllers\PatientTreatmentController;
use App\Http\Controllers\DoctorDashboardController;
use App\Http\Controllers\LabResultController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\DoctorCheckupController;
use App\Http\Controllers\StaffPatientTreatmentController;
use App\Http\Controllers\staff_PDtreatmentController;
use App\Http\Controllers\PatientScheduleListController;
use App\Http\Controllers\AdminDashboardController;
use App\Http\Controllers\StaffProfileController;

use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\MedicineController;
use App\Http\Controllers\SavePrescriptionController;
use App\Http\Controllers\ReadyMedicineController;

use App\Http\Controllers\ADMIN_ADDHCproviderController;

use App\Http\Controllers\ListPrescriptionsController;

use App\Http\Controllers\EmployeeStatusController;

use App\Http\Controllers\StatusController;

use App\Http\Controllers\DoctorStatusController;

use App\Http\Controllers\CUpSidePatientTreatController;

use App\Http\Controllers\ReadyPrescriptionController;

use App\Http\Controllers\PatientAlertController;

use App\Http\Controllers\QueueController;

use App\Http\Controllers\DoctorAssignmentController;
use App\Http\Controllers\AgeDistributionController;
use App\Http\Controllers\ArchiveController;
use App\Http\Controllers\EmployeeArchiveController;

use App\Http\Controllers\DoctorPrescriptionsViewController;

//for ecommerce parts
use App\Http\Controllers\SupplyController;
use App\Http\Controllers\MedicalSupplyController;
use App\Http\Controllers\ProductReviewController;
use App\Http\Controllers\UserProdReviewController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\MedsProdController;
use App\Http\Controllers\PatientOrdersController;
use App\Http\Controllers\ProductDetailModalController;
use App\Http\Controllers\OrderController;

// CSRF Cookie Route - MUST BE FIRST
Route::get('/sanctum/csrf-cookie', function (Request $request) {
    return response()->noContent()->withHeaders([
        'Access-Control-Allow-Origin' => 'https://dialiease-4un0.onrender.com',
        'Access-Control-Allow-Credentials' => 'true',
        'Access-Control-Allow-Methods' => 'GET, POST, PUT, DELETE, OPTIONS',
        'Access-Control-Allow-Headers' => 'Accept, Authorization, Content-Type, X-Requested-With, X-CSRF-TOKEN',
    ]);
});

// Health Check Route
Route::get('/', function () {
    return response()->json(['message' => 'Backend API connected ✅']);
});

// Public Routes
Route::post('/login', [LoginController::class, 'login'])->name('api.login');

// Employee Registration & Validation
Route::post('/validate-employee', [ValidateEmployeeController::class, 'validateEmployee']);
Route::post('/employee-register', [RegisterController::class, 'employeeRegister']);
Route::post('/employee-change-credentials', [RegisterController::class, 'employeeChangeCredentials']);
Route::get('/verify-email/{token}', [RegisterController::class, 'verifyEmail']);

// OTP Routes
Route::post('/update-employee-email', [OTPController::class, 'updateEmail']);
Route::post('/verify-email-otp', [OTPController::class, 'verifyOTP']);
Route::post('/resend-email-otp', [OTPController::class, 'resendOTP']);

// Healthcare Provider Registration
Route::post('/hcp-register', [HCPRegisterController::class, 'register']);

// Patient Registration Routes
Route::post('/patient/check-email', [PatientRegistrationController::class, 'checkEmail']);
Route::post('/patient/send-otp', [PatientRegistrationController::class, 'sendOtp']);
Route::post('/patient/verify-otp', [PatientRegistrationController::class, 'verifyOtp']);
Route::get('/schedules/available-dates', [PatientRegistrationController::class, 'getAvailableDates']);
Route::post('/patient/register', [PatientRegistrationController::class, 'registerPatient']);
Route::post('/patient/generate-certificate', [PatientRegistrationController::class, 'generateCertificate']);

// Patient Phone OTP Routes
Route::prefix('patient')->group(function () {
    Route::middleware('throttle:5,1')->post('/send-phone-otp', [PatientRegistrationController::class, 'sendPhoneOtp']);
    Route::middleware('throttle:10,1')->post('/verify-phone-otp', [PatientRegistrationController::class, 'verifyPhoneOtp']);
});

// Password Reset Routes
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::post('/resend-otp', [ForgotPasswordController::class, 'resendOtp']);

// Public Image Route
Route::get('/medical-supply-image/{filename}', [MedsProdController::class, 'serveImage']);

// Test Routes
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Backend API is working!',
        'timestamp' => now(),
        'environment' => app()->environment()
    ]);
});

Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'service' => 'E-commerce API']);
});

// IoT Routes
Route::prefix('iot')->group(function () {
    Route::get('health', [IotController::class, 'health']);
    Route::get('device-status', [IotController::class, 'getDeviceStatus']);
    Route::get('weight', [IotController::class, 'getWeight']);
    Route::get('status', [IotController::class, 'getStatus']);
    
    Route::post('weight', [IotController::class, 'storeWeight']);
    Route::post('status', [IotController::class, 'updateStatus']);
    Route::post('device-status', [IotController::class, 'updateDeviceStatus']);
    Route::post('connect', [IotController::class, 'connectDevice']);
});

// Public Product Reviews
Route::get('/reviews/product/{supplyID}', [ProductReviewController::class, 'getProductReviews']);

// Test Orders Route
Route::get('/test/patient/orders', [OrderController::class, 'getTestOrders']);

// Age Distribution Route
Route::get('/age-distribution', [AgeDistributionController::class, 'getAgeDistribution']);

// ==================== PROTECTED ROUTES (Require Authentication) ====================
Route::middleware('auth:sanctum')->group(function () {
    
    // User & Profile Routes
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    });
    
    Route::post('/logout', [LoginController::class, 'logout']);
    Route::get('/profile', [UserController::class, 'getProfile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    Route::get('/users/{id}', [UserController::class, 'getUser'])->middleware('can:view,user');

    // Credential Management
    Route::post('/send-verification-code', [LoginController::class, 'sendVerificationCode']);
    Route::post('/verify-email', [LoginController::class, 'verifyEmail']);
    Route::post('/activate-account', [LoginController::class, 'activateAccount']);
    Route::post('/complete-registration', [LoginController::class, 'completeRegistration']);

    // Patient Terms
    Route::get('/patient/terms-status', [PatientRegistrationController::class, 'checkTermsStatus']);
    Route::post('/patient/accept-terms', [PatientRegistrationController::class, 'acceptTerms']);

    // ==================== ADMIN ROUTES ====================
    Route::prefix('admin')->group(function () {
        // Dashboard
        Route::get('/dashboard-stats', [AdminDashboardController::class, 'getDashboardStats']);
        Route::get('/appointment-counts', [AdminDashboardController::class, 'getAppointmentCounts']);
        Route::get('/age-distribution', [AdminDashboardController::class, 'getAgeDistribution']);
        
        // Reminders
        Route::get('/reminders', [AdminDashboardController::class, 'getReminders']);
        Route::post('/reminders', [AdminDashboardController::class, 'addReminder']);
        Route::put('/reminders/{id}', [AdminDashboardController::class, 'updateReminder']);
        Route::delete('/reminders/{id}', [AdminDashboardController::class, 'deleteReminder']);

        // Providers
        Route::get('/providers', [ProviderController::class, 'index']);
        Route::post('/providers', [ProviderController::class, 'store']);
        Route::put('/providers/{id}', [ProviderController::class, 'update']);
        Route::put('/providers/{id}/activate', [ProviderController::class, 'activate']);
        Route::put('/providers/{id}/deactivate', [ProviderController::class, 'deactivate']);
        Route::get('/generate-numbers', [ProviderController::class, 'generateNumbers']);

        // Healthcare Providers
        Route::post('/pre-register-hcprovider', [ADMIN_ADDHCproviderController::class, 'preRegisterHCprovider']);
        Route::post('/bulk-register-hcproviders', [ADMIN_ADDHCproviderController::class, 'bulkRegisterHCproviders']);
        Route::get('/check-doc-license/{license}', [ADMIN_ADDHCproviderController::class, 'checkDocLicense']);
        Route::post('/generate-pre-register-pdf', [ADMIN_ADDHCproviderController::class, 'generatePreRegisterPDF']);
        Route::get('/providers', [ADMIN_ADDHCproviderController::class, 'listProviders']);

        // Patient Management
        Route::get('/patient-list', [PatientController::class, 'index']);
        Route::get('/audit-logs', [AuditLogController::class, 'index']);
    });

    // ==================== PATIENT ROUTES ====================
    Route::prefix('patient')->group(function () {
        // Dashboard
        Route::get('/dashboard', [PatientDashboardController::class, 'getDashboardData']);
        
        // Treatments
        Route::prefix('treatments')->group(function () {
            Route::get('/', [PatientTreatmentController::class, 'getTreatments']);
            Route::get('/stats', [PatientTreatmentController::class, 'getTreatmentStats']);
            Route::get('/history', [Treatments_Controller::class, 'getTreatmentHistory']);
            Route::get('/{id}', [Treatments_Controller::class, 'getTreatmentDetails']);
            Route::post('/', [Treatments_Controller::class, 'startTreatment']);
            Route::post('/complete', [Treatments_Controller::class, 'endTreatment']);
            Route::get('/today-count', [TreatmentController::class, 'getTodayTreatmentCount']);
            Route::get('/ongoing', [PatientDashboardController::class, 'getOngoingTreatment']);
            Route::get('/recent', [PatientDashboardController::class, 'getRecentTreatments']);
        });

        // Schedules
        Route::get('/upcoming-checkups', [PatientScheduleController::class, 'upcomingCheckups']);
        Route::get('/confirmation-status', [PatientScheduleController::class, 'confirmationStatus']);
        Route::post('/daily-limit-status', [PatientScheduleController::class, 'dailyLimitStatus']);
        Route::post('/confirm-appointment', [PatientScheduleController::class, 'confirmAppointment']);
        Route::post('/request-reschedule', [PatientScheduleController::class, 'requestReschedule']);

        // Alerts
        Route::get('/doctor-alerts', [PatientAlertController::class, 'getDoctorAlerts']);
        Route::post('/confirm-emergency', [PatientAlertController::class, 'confirmEmergency']);

        // E-commerce
        Route::prefix('supplies')->group(function () {
            Route::get('/', [MedicalSupplyController::class, 'getMedicalSuppliesForPatient']);
            Route::get('/{id}', [MedicalSupplyController::class, 'getMedicalSupplyForPatient']);
            Route::get('/category/{category}', [MedicalSupplyController::class, 'getSuppliesByCategoryForPatient']);
            Route::get('/search', [MedicalSupplyController::class, 'searchMedicalSuppliesForPatient']);
            Route::get('/categories', [MedsProdController::class, 'getCategoriesForPatient']);
            Route::get('/featured', [MedsProdController::class, 'getFeaturedSuppliesForPatient']);
            Route::get('/low-stock', [MedsProdController::class, 'getLowStockSuppliesForPatient']);
            Route::get('/related/{id}', [MedsProdController::class, 'getRelatedSupplies']);
        });

        // Reviews
        Route::post('/reviews/submit', [ProductReviewController::class, 'submitReview']);
        Route::get('/product/{supplyID}', [ProductReviewController::class, 'getProductReviews']);

        // Cart
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'getUserCart']);
            Route::post('/add', [CartController::class, 'addToCart']);
            Route::put('/update/{cartID}', [CartController::class, 'updateCartItem']);
            Route::delete('/remove/{cartID}', [CartController::class, 'removeFromCart']);
            Route::delete('/clear', [CartController::class, 'clearCart']);
            Route::get('/count', [CartController::class, 'getCartCount']);
            Route::post('/validate-checkout', [CartController::class, 'validateCartForCheckout']);
        });

        // Wishlist
        Route::prefix('wishlist')->group(function () {
            Route::get('/', [WishlistController::class, 'getUserWishlist']);
            Route::post('/add', [WishlistController::class, 'addToWishlist']);
            Route::delete('/remove/{supplyID}', [WishlistController::class, 'removeFromWishlist']);
        });

        // Orders
        Route::get('/orders', [OrderController::class, 'getPatientOrders']);
        Route::get('/orders/{orderID}', [OrderController::class, 'getOrderDetails']);
        Route::post('/orders/{orderID}/cancel', [OrderController::class, 'cancelOrder']);
    });

    // ==================== DOCTOR ROUTES ====================
    Route::prefix('doctor')->group(function () {
        // Dashboard
        Route::get('/dashboard', [DoctorDashboardController::class, 'getDashboardData']);
        Route::post('/mark-completed', [DoctorDashboardController::class, 'markAsCompleted']);
        Route::post('/approve-reschedule', [DoctorDashboardController::class, 'approveReschedule']);
        Route::post('/create-prescription', [DoctorDashboardController::class, 'createPrescription']);

        // Treatments & Alerts
        Route::get('/patient-treatments', [DoctorTreatmentController::class, 'getPatientTreatments']);
        Route::get('/all-patient-treatments', [DoctorTreatmentController::class, 'getAllPatientTreatments']);
        Route::post('/send-patient-alert', [DoctorTreatmentController::class, 'sendPatientAlert']);
        Route::post('/recommend-emergency', [DoctorTreatmentController::class, 'recommendEmergency']);
        Route::post('/recommend-emergency-to-all', [DoctorTreatmentController::class, 'recommendEmergencyToAll']);

        // Checkups & Prescriptions
        Route::get('/checkup', [DoctorCheckupController::class, 'getCheckupData']);
        Route::post('/start-checkup', [DoctorCheckupController::class, 'startCheckup']);
        Route::post('/complete-checkup', [DoctorCheckupController::class, 'completeCheckup']);
        Route::post('/submit-prescription', [DoctorCheckupController::class, 'submitPrescription']);
        Route::get('/patient/{patientId}/prescriptions', [DoctorCheckupController::class, 'getPatientPrescriptions']);

        // Assignments & Queues
        Route::get('/assigned-patients', [DoctorAssignmentController::class, 'getAssignedPatients']);
        Route::get('/my-queues', [QueueController::class, 'getDoctorQueues']);
    });

    // ==================== STAFF ROUTES ====================
    Route::prefix('staff')->group(function () {
        // Dashboard
        Route::get('/dashboard', [StaffDashboardController::class, 'getDashboardData']);
        Route::post('/send-reminder', [StaffDashboardController::class, 'sendReminderEmail']);
        Route::post('/mark-completed', [StaffDashboardController::class, 'markAsCompleted']);
        Route::post('/mark-notifications-read', [StaffDashboardController::class, 'markNotificationsAsRead']);
        Route::post('/update-status', [StaffDashboardController::class, 'updateStatus']);

        // Appointments
        Route::get('/reschedule-requests', [StaffDashboardController::class, 'getRescheduleRequests']);
        Route::get('/missed-appointments', [StaffDashboardController::class, 'getMissedAppointments']);
        Route::get('/completed-checkups', [StaffDashboardController::class, 'getCompletedCheckups']);
        Route::post('/reschedule-missed', [StaffDashboardController::class, 'rescheduleMissedAppointment']);
        Route::post('/reschedule-missed-batch', [StaffDashboardController::class, 'rescheduleMissedBatch']);
        Route::post('/manual-reschedule-missed', [StaffDashboardController::class, 'manualRescheduleMissed']);
        Route::post('/complete-checkup', [StaffDashboardController::class, 'completeCheckup']);
        Route::post('/archive-reschedule-reason', [StaffDashboardController::class, 'archiveRescheduleReason']);

        // Queue Management
        Route::get('/today-queues', [QueueController::class, 'getTodayQueues']);
        Route::get('/doctors-on-duty', [QueueController::class, 'getDoctorsOnDuty']);
        Route::get('/doctors-status', [DoctorStatusController::class, 'getDoctorsStatus']);
        Route::get('/patients', [QueueController::class, 'getPatients']);
        Route::get('/enhanced-patient-data/{userID}', [QueueController::class, 'getEnhancedPatientData']);
        Route::post('/update-queue-status', [QueueController::class, 'updateQueueStatus']);
        Route::post('/start-queue', [QueueController::class, 'startQueue']);
        Route::post('/add-to-queue', [QueueController::class, 'addToQueue']);
        Route::post('/skip-queue', [QueueController::class, 'skipQueue']);
        Route::post('/update-emergency-statuses', [QueueController::class, 'updateEmergencyStatuses']);
        Route::post('/prioritize-emergency-patient', [QueueController::class, 'prioritizeEmergencyPatient']);
        Route::post('/send-to-emergency', [QueueController::class, 'sendToEmergency']);

        // Treatments
        Route::get('/treatments', [staff_PDtreatmentController::class, 'getAllTreatments']);
        Route::get('/treatments/patient/{patientId}', [staff_PDtreatmentController::class, 'getPatientTreatments']);
    });

    // ==================== COMMON ROUTES ====================
    
    // Patient Management
    Route::get('/patients', [staff_PatientListController::class, 'index']);
    Route::put('/patients/{id}/archive', [staff_PatientListController::class, 'archivePatient']);
    Route::get('/patient-history/{id}', [staff_PatientListController::class, 'getPatientHistory']);
    
    // Patient Schedules
    Route::prefix('patient-schedules')->group(function () {
        Route::get('/', [PatientScheduleListController::class, 'index']);
        Route::get('/upcoming', [PatientScheduleListController::class, 'upcoming']);
        Route::get('/missed', [PatientScheduleListController::class, 'missed']);
        Route::get('/completed', [PatientScheduleListController::class, 'completed']);
    });

    // Archive Management
    Route::get('/archives', [ArchiveController::class, 'getArchivedRecords']);
    Route::post('/archives/{archiveId}/restore', [ArchiveController::class, 'restoreArchive']);
    Route::put('/patients/{userID}/archive', [ArchiveController::class, 'archivePatient']);
    
    // Employee Archive
    Route::delete('/employees/{userID}/archive', [EmployeeArchiveController::class, 'archiveEmployee']);
    Route::get('/employees/archived', [EmployeeArchiveController::class, 'getArchivedEmployees']);
    Route::post('/employees/{archiveId}/restore', [EmployeeArchiveController::class, 'restoreEmployee']);
    
    // Employee Status
    Route::get('/employees/statuses/today', [EmployeeStatusController::class, 'getEmployeeStatus']);
    
    // Status Update
    Route::post('/status/update', [StatusController::class, 'updateStatus']);

    // Queue Statistics
    Route::get('/queue-statistics', [QueueController::class, 'getQueueStatistics']);

    // ==================== PRESCRIPTION ROUTES ====================
    Route::prefix('prescriptions')->group(function () {
        Route::get('/', [PrescriptionController::class, 'index']);
        Route::get('/medicines/search', [MedicineController::class, 'searchMedicines']);
        Route::post('/medicines', [MedicineController::class, 'addMedicine']);
        Route::post('/save', [SavePrescriptionController::class, 'savePrescription']);
        Route::get('/doctor/prescriptions', [SavePrescriptionController::class, 'getAllPatientPrescriptions']);
    });

    // Ready Medicines
    Route::prefix('ready-medicines')->group(function () {
        Route::get('/', [ReadyMedicineController::class, 'getReadyMedicines']);
        Route::post('/add', [ReadyMedicineController::class, 'addReadyMedicine']);
        Route::post('/create', [ReadyMedicineController::class, 'createReadyMedicine']);
        Route::put('/{id}', [ReadyMedicineController::class, 'updateReadyMedicine']);
        Route::delete('/{id}', [ReadyMedicineController::class, 'deleteReadyMedicine']);
    });

    // ==================== E-COMMERCE MANAGEMENT ROUTES ====================
    
    // Supplies Management
    Route::prefix('supplies')->group(function () {
        Route::get('/', [MedicalSupplyController::class, 'getSupplies']);
        Route::get('/list', [SupplyController::class, 'getSupplies']);
        Route::get('/{id}', [MedicalSupplyController::class, 'getSupply']);
        Route::get('/low-stock', [MedicalSupplyController::class, 'getLowStockSupplies']);
        Route::get('/low-stock/list', [SupplyController::class, 'getLowStockSupplies']);
        Route::post('/add', [MedicalSupplyController::class, 'addSupply']);
        Route::put('/update/{id}', [MedicalSupplyController::class, 'updateSupply']);
        Route::post('/update/{id}', [MedicalSupplyController::class, 'updateSupply']);
        Route::patch('/quick-update/{id}', [MedicalSupplyController::class, 'quickUpdateSupply']);
        Route::post('/quick-update/{id}', [MedicalSupplyController::class, 'quickUpdateSupply']);
        Route::delete('/delete/{id}', [MedicalSupplyController::class, 'deleteSupply']);
        Route::get('/analytics/dashboard', [MedicalSupplyController::class, 'getAnalytics']);
        
        // Supply Controller routes
        Route::post('/supplies/update/{id}', [SupplyController::class, 'updateSupply']);
        Route::delete('/supplies/delete/{id}', [SupplyController::class, 'deleteSupply']);
    });

    // Product Management
    Route::get('/products/{id}', [ProductDetailModalController::class, 'getProduct']);
    Route::put('/products/{id}', [ProductDetailModalController::class, 'updateProduct']);
    Route::delete('/products/{id}', [ProductDetailModalController::class, 'deleteProduct']);
    Route::post('/products/{id}/quick-update', [ProductDetailModalController::class, 'quickUpdate']);

    // Reviews Management
    Route::prefix('reviews')->group(function () {
        Route::get('/all', [MedicalSupplyController::class, 'getAllReviews']);
        Route::patch('/{reviewID}/status', [MedicalSupplyController::class, 'updateReviewStatus']);
        Route::delete('/{reviewID}/delete', [MedicalSupplyController::class, 'deleteReview']);
        Route::get('/stats/dashboard', [MedicalSupplyController::class, 'getReviewStats']);
    });

    Route::prefix('user-product-reviews')->group(function () {
        Route::get('/', [UserProdReviewController::class, 'getProductReviews']);
        Route::get('/statistics', [UserProdReviewController::class, 'getReviewStatistics']);
        Route::put('/{id}/status', [UserProdReviewController::class, 'updateReviewStatus']);
        Route::delete('/{id}', [UserProdReviewController::class, 'deleteProductReview']);
    });

    // Orders Management
    Route::get('/patient-orders', [PatientOrdersController::class, 'getAllOrders']);
    Route::get('/patient-orders/grouped-by-date', [PatientOrdersController::class, 'getOrdersGroupedByDate']);
    Route::get('/patient-orders/{orderID}', [PatientOrdersController::class, 'getOrderDetails']);
    Route::put('/patient-orders/{orderID}/status', [PatientOrdersController::class, 'updateOrderStatus']);
    Route::get('/patient-orders/statistics/overview', [PatientOrdersController::class, 'getOrdersStatistics']);
    Route::get('/patient-orders/analytics/sales', [PatientOrdersController::class, 'getSalesAnalytics']);
    Route::get('/patient-orders/reports/sales', [PatientOrdersController::class, 'downloadSalesReport']);
    Route::get('/patient-orders/metrics/overview', [PatientOrdersController::class, 'getOrderMetrics']);
    Route::get('/patient-orders/search/quick', [PatientOrdersController::class, 'searchOrders']);

    // ==================== PATIENT TREATMENT DATA ====================
    Route::get('/patient-treatments/{patientId}', [CUpSidePatientTreatController::class, 'getPatientTreatments']);
    Route::get('/patient-statistics/{patientId}', [CUpSidePatientTreatController::class, 'getPatientStatistics']);
    Route::get('/patient-treatment-summary/{patientId}', [CUpSidePatientTreatController::class, 'getPatientTreatmentSummary']);
    Route::get('/v1/staff-patient-treatments/{patientId}', [StaffPatientTreatmentController::class, 'staffGetPatientTreatments']);
    Route::get('/v1/staff-patient-treatments-stats/{patientId}', [StaffPatientTreatmentController::class, 'staffGetTreatmentStats']);

    // Lab Results
    Route::post('/upload-lab-result', [LabResultController::class, 'upload']);

    // Audit Logs
    Route::post('/audit-logs', function(Request $request) {
        $user = $request->user();
        
        DB::table('audittrail')->insert([
            'userID' => $user->userID,
            'action' => $request->action,
            'timestamp' => now()
        ]);
        
        return response()->json(['message' => 'Audit log created']);
    });
});

// Test route for reviews
Route::get('/test-reviews', function () {
    try {
        $reviews = DB::table('product_reviews')->get();
        return response()->json([
            'success' => true,
            'total_reviews' => $reviews->count(),
            'reviews' => $reviews
        ]);
    } catch (\Exception $e) {
        return response()->json([
            'success' => false,
            'error' => $e->getMessage()
        ], 500);
    }
});