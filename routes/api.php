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
use App\Http\Controllers\CheckoutModalController;
use App\Http\Controllers\PaymongoController;
use App\Http\Controllers\MedsProdController;
use App\Http\Controllers\PatientOrdersController;
use App\Http\Controllers\ProductDetailModalController;
use App\Http\Controllers\OrderController;


Route::get('/', function () {
    return response()->json(['message' => 'Backend API connected ✅']);
});

// Public Routes
Route::post('/login', [LoginController::class, 'login'])->name('api.login');

Route::post('/validate-employee', [RegisterController::class, 'validateEmployee']);
Route::post('/employee-register', [RegisterController::class, 'employeeRegister']);
Route::post('/employee-change-credentials', [RegisterController::class, 'employeeChangeCredentials']);
Route::get('/verify-email/{token}', [RegisterController::class, 'verifyEmail']);


//prescription and medicine routes
Route::middleware('auth:sanctum')->group(function () {
    Route::prefix('prescriptions')->group(function () {
        Route::get('/', [PrescriptionController::class, 'index']);
        Route::post('/medicines', [PrescriptionController::class, 'storeMedicine']);
    });
});

// Patient alert routes
Route::middleware('auth:sanctum')->group(function () {
    // Doctor alerts routes
    Route::get('/patient/doctor-alerts', [PatientAlertController::class, 'getDoctorAlerts']);
    Route::post('/patient/confirm-emergency', [PatientAlertController::class, 'confirmEmergency']);
});




// Prescription routes
Route::prefix('prescriptions')->group(function () {
    Route::get('/medicines/search', [MedicineController::class, 'searchMedicines']);
    Route::post('/medicines', [MedicineController::class, 'addMedicine']);
    Route::post('/save', [SavePrescriptionController::class, 'savePrescription']);
    Route::get('/doctor/prescriptions', [SavePrescriptionController::class, 'getAllPatientPrescriptions']);
});

// Ready Medicines Routes
Route::prefix('ready-medicines')->group(function () {
    Route::get('/', [ReadyMedicineController::class, 'getReadyMedicines']);
    Route::post('/add', [ReadyMedicineController::class, 'addReadyMedicine']);
    Route::post('/create', [ReadyMedicineController::class, 'createReadyMedicine']);
    Route::put('/{id}', [ReadyMedicineController::class, 'updateReadyMedicine']);
    Route::delete('/{id}', [ReadyMedicineController::class, 'deleteReadyMedicine']);
});


// Queue management routes
Route::middleware(['auth:sanctum'])->group(function () {
    // Staff routes
    Route::get('/staff/today-queues', [QueueController::class, 'getTodayQueues']);
    Route::get('/staff/doctors-on-duty', [QueueController::class, 'getDoctorsOnDuty']);
    Route::post('/staff/update-queue-status', [QueueController::class, 'updateQueueStatus']);
    Route::post('/staff/start-queue', [QueueController::class, 'startQueue']);
    Route::post('/staff/add-to-queue', [QueueController::class, 'addToQueue']);
    Route::get('/staff/patients', [QueueController::class, 'getPatients']);
    Route::post('/staff/skip-queue', [QueueController::class, 'skipQueue']);
    Route::post('/staff/update-emergency-statuses', [QueueController::class, 'updateEmergencyStatuses']);
    Route::post('/staff/prioritize-emergency-patient', [QueueController::class, 'prioritizeEmergencyPatient']);
    Route::post('/staff/send-to-emergency', [QueueController::class, 'sendToEmergency']);
    Route::get('/staff/enhanced-patient-data/{userID}', [QueueController::class, 'getEnhancedPatientData']);

    // Doctor routes
    Route::get('/doctor/my-queues', [QueueController::class, 'getDoctorQueues']);
    
    // Common routes
    Route::get('/queue-statistics', [QueueController::class, 'getQueueStatistics']);
});


// Patient Registration Routes
Route::post('/validate-employee', [RegisterController::class, 'validateEmployee']);
Route::post('/send-otp', [RegisterController::class, 'sendOTP']);
Route::post('/verify-otp', [RegisterController::class, 'verifyOTP']);
Route::post('/employee-register', [RegisterController::class, 'employeeRegister']);
Route::post('/employee-change-credentials', [RegisterController::class, 'employeeChangeCredentials']);
Route::get('/verify-email/{token}', [RegisterController::class, 'verifyEmail']);
// Protected Routes (Requires Sanctum Authentication)
Route::middleware('auth:sanctum')->group(function () {
    // User route
    Route::get('/user', function (Request $request) {
        return response()->json([
            'success' => true,
            'user' => $request->user()
        ]);
    });
    
    Route::post('/logout', [LoginController::class, 'logout']);

    Route::middleware(['auth:sanctum'])->group(function () {
        // Complete registration for pre-registered users
        Route::post('complete-registration', [LoginController::class, 'completeRegistration']);
    });
// Credential change routes - accessible with temporary token
Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/send-verification-code', [LoginController::class, 'sendVerificationCode']);
    Route::post('/verify-email', [LoginController::class, 'verifyEmail']);
    Route::post('/activate-account', [LoginController::class, 'activateAccount']);
    Route::post('/logout', [LoginController::class, 'logout']);
});

    // Providers
    Route::prefix('providers')->group(function () {
        Route::get('/', [ProviderController::class, 'index']);
        Route::post('/', [ProviderController::class, 'store']);
        Route::put('/{id}', [ProviderController::class, 'update']);
        Route::put('/{id}/deactivate', [ProviderController::class, 'deactivate']);
        Route::put('/{id}/activate', [ProviderController::class, 'activate']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::put('/patients/{userID}/archive', [ArchiveController::class, 'archivePatient']);
    // Route::get('/archives', [ArchiveController::class, 'getArchivedRecords']);
    // Route::post('/archives/{archiveId}/restore', [ArchiveController::class, 'restoreArchive']);
    // // Add this to your routes/api.php
Route::get('/archives', [ArchiveController::class, 'getArchivedRecords']);
Route::post('/archives/{archiveId}/restore', [ArchiveController::class, 'restoreArchive']);
});


// Employee archive routes
Route::middleware('auth:sanctum')->group(function () {
    Route::delete('/employees/{userID}/archive', [EmployeeArchiveController::class, 'archiveEmployee']);
    Route::get('/employees/archived', [EmployeeArchiveController::class, 'getArchivedEmployees']);
    Route::post('/employees/{archiveId}/restore', [EmployeeArchiveController::class, 'restoreEmployee']);
});

// Patient List
Route::get('/patients', [staff_PatientListController::class, 'index']);
Route::put('/patients/{id}/archive', [staff_PatientListController::class, 'archivePatient'])->middleware('auth:sanctum');
Route::get('/patient-history/{id}', [staff_PatientListController::class, 'getPatientHistory'])->middleware('auth:sanctum');

// Audit Log
Route::post('/audit-logs', function(Request $request) {
    $user = $request->user();
    
    DB::table('audittrail')->insert([
        'userID' => $user->userID,
        'action' => $request->action,
        'timestamp' => now()
    ]);
    
    return response()->json(['message' => 'Audit log created']);
})->middleware('auth:sanctum');

// Admin Routes
Route::prefix('admin')->group(function () {
    Route::get('/dashboard-stats', [AdminController::class, 'getDashboardStats']);
    Route::get('/patient-list', [PatientController::class, 'index']);
    Route::get('/audit-logs', [AuditLogController::class, 'index']);
});


//doctor routes for patient treatments
Route::prefix('doctor')->middleware('auth:sanctum')->group(function () {
    Route::get('/patient-treatments', [DoctorTreatmentController::class, 'getPatientTreatments']);
    Route::post('/recommend-emergency', [DoctorTreatmentController::class, 'recommendEmergency']);
    Route::post('/recommend-emergency-to-all', [DoctorTreatmentController::class, 'recommendEmergencyToAll']);
});


// Patient Routes
Route::prefix('patient')->middleware('auth:sanctum')->group(function () {
    // Dashboard routes
    Route::get('/dashboard', [PatientDashboardController::class, 'getDashboardData']);
    
    // Treatment routes
    Route::prefix('/treatments')->group(function () {
        Route::get('/', [TreatmentController::class, 'getPatientTreatments']);
        Route::post('/', [TreatmentController::class, 'startTreatment']);
        Route::post('/{id}/complete', [TreatmentController::class, 'completeTreatment']);
        Route::get('/today-count', [TreatmentController::class, 'getTodayTreatmentCount']);
        Route::get('/ongoing', [PatientDashboardController::class, 'getOngoingTreatment']);
        Route::get('/recent', [PatientDashboardController::class, 'getRecentTreatments']);
    });

    // Checkup routes
    Route::get('/upcoming-checkups', [PatientScheduleController::class, 'getUpcomingCheckups']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Staff Dashboard
    Route::get('/staff/dashboard', [StaffDashboardController::class, 'getDashboardData']);
    Route::post('/staff/send-reminder', [StaffDashboardController::class, 'sendReminderEmail']);
    Route::post('/staff/mark-completed', [StaffDashboardController::class, 'markAsCompleted']);
    Route::post('/staff/mark-notifications-read', [StaffDashboardController::class, 'markNotificationsAsRead']);
    Route::post('/staff/reschedule-missed', [StaffDashboardController::class, 'rescheduleMissedAppointment']);
    Route::post('/staff/reschedule-missed-batch', [StaffDashboardController::class, 'rescheduleMissedBatch']);
    Route::get('/staff/reschedule-requests', [StaffDashboardController::class, 'getRescheduleRequests']);
    Route::get('/staff/missed-appointments', [StaffDashboardController::class, 'getMissedAppointments']);
    Route::post('/staff/update-status', [StaffDashboardController::class, 'updateStatus']);

    Route::get('/staff/completed-checkups', [StaffDashboardController::class, 'getCompletedCheckups']);
    Route::post('/staff/complete-checkup', [StaffDashboardController::class, 'completeCheckup'])->middleware('auth:api');
    Route::post('/staff/manual-reschedule-missed', [StaffDashboardController::class, 'manualRescheduleMissed']);
    

    //archiving the letter
    Route::post('/staff/archive-reschedule-reason', [StaffDashboardController::class, 'archiveRescheduleReason']);
});


//for view status
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/employees/statuses/today', [EmployeeStatusController::class, 'getEmployeeStatus']);
});

Route::middleware(['auth:sanctum'])->group(function () {
    // Patient orders routes
    Route::get('/patient/orders', [OrderController::class, 'getPatientOrders']);
    Route::get('/patient/orders/{orderID}', [OrderController::class, 'getOrderDetails']);
    Route::post('/patient/orders/{orderID}/cancel', [OrderController::class, 'cancelOrder']);
    
    // Debug route
    Route::get('/patient/orders-debug', [OrderController::class, 'debugOrders']);
});

// Test route without authentication (for debugging)
Route::get('/test/patient/orders', [OrderController::class, 'getTestOrders']);


// Patient Registration Routes (PatientRegistrationController)
Route::post('/patient/check-email', [PatientRegistrationController::class, 'checkEmail']);
Route::post('/patient/send-otp', [PatientRegistrationController::class, 'sendOtp']);
Route::post('/patient/verify-otp', [PatientRegistrationController::class, 'verifyOtp']);
Route::get('/schedules/available-dates', [PatientRegistrationController::class, 'getAvailableDates']);
Route::post('/patient/register', [PatientRegistrationController::class, 'registerPatient']);
Route::post('/patient/generate-certificate', [PatientRegistrationController::class, 'generateCertificate']);


Route::prefix('patient')->group(function () {
    // Rate limited routes
    Route::middleware('throttle:5,1')->post('/send-phone-otp', [PatientRegistrationController::class, 'sendPhoneOtp']);
    Route::middleware('throttle:10,1')->post('/verify-phone-otp', [PatientRegistrationController::class, 'verifyPhoneOtp']);
});


//////

Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('patient/treatments')->group(function () {
        
        Route::middleware('auth:sanctum')->post('/patient/treatments', [Treatments_Controller::class, 'startTreatment']);
        Route::get('/history', [Treatments_Controller::class, 'getTreatmentHistory']);
        Route::get('/{id}', [Treatments_Controller::class, 'getTreatmentDetails']);
        Route::post('/complete', [Treatments_Controller::class, 'endTreatment']);
    });
});



// Route::prefix('patient')->middleware('auth:api')->group(function() {
//     Route::post('/treatments/end', [Treatments_Controller::class, 'endTreatment']);
//     Route::get('/patient/treatments/ongoing', [Treatments_Controller::class, 'checkOngoingTreatment']);
// });

Route::middleware('auth:sanctum')->group(function () {
    // Patient schedule routes
    Route::prefix('patient')->group(function () {
        Route::get('/upcoming-checkups', [PatientScheduleController::class, 'getUpcomingCheckups']);
        Route::post('/request-reschedule', [PatientScheduleController::class, 'requestReschedule']);
    });
});


Route::middleware(['auth:sanctum'])->group(function () {
    // Patient schedule routes
    Route::prefix('patient')->group(function () {
        Route::get('/upcoming-checkups', [PatientScheduleController::class, 'upcomingCheckups']);
        Route::get('/confirmation-status', [PatientScheduleController::class, 'confirmationStatus']);
        Route::post('/daily-limit-status', [PatientScheduleController::class, 'dailyLimitStatus']);
        Route::post('/confirm-appointment', [PatientScheduleController::class, 'confirmAppointment']);
        Route::post('/request-reschedule', [PatientScheduleController::class, 'requestReschedule']);
    });
});


Route::middleware('auth:api')->get('/user', function (Request $request) {
    return $request->user();
});

Route::prefix('admin')->middleware(['auth:api'])->group(function () {
    // Healthcare providers routes
    Route::get('/providers', [ProviderController::class, 'index']);
    Route::post('/providers', [ProviderController::class, 'store']);
    Route::put('/providers/{id}', [ProviderController::class, 'update']);
    Route::put('/providers/{id}/activate', [ProviderController::class, 'activate']);
    Route::put('/providers/{id}/deactivate', [ProviderController::class, 'deactivate']);
});


Route::middleware('auth:sanctum')->group(function () {
    // User profile routes
    Route::get('/profile', [UserController::class, 'getProfile']);
    Route::put('/profile', [UserController::class, 'updateProfile']);
    
    // Admin-only route
    Route::get('/users/{id}', [UserController::class, 'getUser'])->middleware('can:view,user');
});

// routes/api.php
Route::prefix('admin')->group(function () {
    Route::get('/generate-numbers', [ProviderController::class, 'generateNumbers']);
    Route::post('/admin/providers', [ProviderController::class, 'store'])->middleware('auth:api');
});


//for staff and nurse access to patient schedules
Route::middleware('auth:sanctum')->group(function () {
    // Patient schedules routes
    Route::prefix('patient-schedules')->group(function () {
        Route::get('/', [PatientScheduleListController::class, 'index']);
        Route::get('/upcoming', [PatientScheduleListController::class, 'upcoming']);
        Route::get('/missed', [PatientScheduleListController::class, 'missed']);
        Route::get('/completed', [PatientScheduleListController::class, 'completed']);
    });
});


//Admin Dashboard Routes
Route::middleware(['auth:sanctum'])->group(function() {
    Route::prefix('admin')->group(function() {
        // Dashboard stats
        Route::get('/dashboard-stats', [AdminDashboardController::class, 'getDashboardStats']);
        Route::get('/appointment-counts', [AdminDashboardController::class, 'getAppointmentCounts']);
        
        // Reminders
        Route::get('/reminders', [AdminDashboardController::class, 'getReminders']);
        Route::post('/reminders', [AdminDashboardController::class, 'addReminder']);
        Route::put('/reminders/{id}', [AdminDashboardController::class, 'updateReminder']);
        Route::delete('/reminders/{id}', [AdminDashboardController::class, 'deleteReminder']);

        
        Route::get('/admin/age-distribution', [AdminDashboardController::class, 'getAgeDistribution']);
    });
});



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



Route::middleware('auth:api')->group(function () {
    Route::get('/patient/terms-status', [PatientRegistrationController::class, 'checkTermsStatus']);
    Route::post('/patient/accept-terms', [PatientRegistrationController::class, 'acceptTerms']);
    // ... your other routes ...
});


// Employee Validation Routes
Route::post('/validate-employee', [ValidateEmployeeController::class, 'validateEmployee']);

// OTP Routes
Route::post('/update-employee-email', [OTPController::class, 'updateEmail']);
Route::post('/verify-email-otp', [OTPController::class, 'verifyOTP']);
Route::post('/resend-email-otp', [OTPController::class, 'resendOTP']);

// Employee Registration Routes
Route::post('/hcp-register', [HCPRegisterController::class, 'register']);



Route::middleware(['auth:sanctum'])->group(function () {
    // Treatment history routes
    Route::get('/patient/treatments', [PatientTreatmentController::class, 'getTreatments']);
    Route::get('/patient/treatments/stats', [PatientTreatmentController::class, 'getTreatmentStats']);
});

//for staff and nurse access to patient treatments
Route::middleware(['auth:sanctum'])->prefix('v1')->group(function() {
    Route::get('/staff-patient-treatments/{patientId}', 
        [StaffPatientTreatmentController::class, 'staffGetPatientTreatments']);
    
    Route::get('/staff-patient-treatments-stats/{patientId}', 
        [StaffPatientTreatmentController::class, 'staffGetTreatmentStats']);
});

Route::middleware('auth:api')->group(function () {
    // Staff PD Treatments routes
    Route::get('/staff/treatments', [staff_PDtreatmentController::class, 'getAllTreatments']);
    Route::get('/staff/treatments/patient/{patientId}', [staff_PDtreatmentController::class, 'getPatientTreatments']);
});


Route::middleware(['auth:sanctum'])->group(function () {
    // Status update route (outside doctor prefix)
    Route::post('/status/update', [StatusController::class, 'updateStatus']);


    // Doctor dashboard routes
    Route::prefix('doctor')->group(function () {
        Route::get('/dashboard', [DoctorDashboardController::class, 'getDashboardData']);
        Route::post('/mark-completed', [DoctorDashboardController::class, 'markAsCompleted']);
        Route::post('/approve-reschedule', [DoctorDashboardController::class, 'approveReschedule']);
        Route::post('/create-prescription', [DoctorDashboardController::class, 'createPrescription']);
    });
});


// Doctor's patient assignment routes
Route::middleware(['auth:sanctum'])->group(function () {
    Route::get('/doctor/assigned-patients', [DoctorAssignmentController::class, 'getAssignedPatients']);
});


//for checking the ststus of doctors
Route::middleware(['auth:sanctum'])->group(function () {
    Route::prefix('staff')->group(function () {
        Route::get('/doctors-status', [DoctorStatusController::class, 'getDoctorsStatus']);
    });
});

Route::post('/upload-lab-result', [LabResultController::class, 'upload']);



Route::middleware(['auth:sanctum'])->prefix('admin')->group(function () {
    // Healthcare provider registration routes
    Route::post('pre-register-hcprovider', [ADMIN_ADDHCproviderController::class, 'preRegisterHCprovider']);
    Route::post('bulk-register-hcproviders', [ADMIN_ADDHCproviderController::class, 'bulkRegisterHCproviders']);
    Route::get('check-doc-license/{license}', [ADMIN_ADDHCproviderController::class, 'checkDocLicense']);
    // Fix: Change to POST method for PDF generation with data in body
    Route::post('generate-pre-register-pdf', [ADMIN_ADDHCproviderController::class, 'generatePreRegisterPDF']);
    Route::get('providers', [ADMIN_ADDHCproviderController::class, 'listProviders']);
});


// Add this route to your routes file
Route::get('/patient-treatments/{patientId}', [CUpSidePatientTreatController::class, 'getPatientTreatments']);
Route::get('/patient-statistics/{patientId}', [CUpSidePatientTreatController::class, 'getPatientStatistics']);
Route::get('/patient-treatment-summary/{patientId}', [CUpSidePatientTreatController::class, 'getPatientTreatmentSummary']);


// Doctor routes for patient alerts
Route::prefix('doctor')->middleware('auth:sanctum')->group(function () {
    Route::get('/patient-treatments', [DoctorTreatmentController::class, 'getPatientTreatments']);
    Route::get('/all-patient-treatments', [DoctorTreatmentController::class, 'getAllPatientTreatments']);
    Route::post('/send-patient-alert', [DoctorTreatmentController::class, 'sendPatientAlert']);
});

// routes/api.php (or web.php depending on your setup)
Route::post('/forgot-password', [ForgotPasswordController::class, 'sendOtp']);
Route::post('/verify-otp', [ForgotPasswordController::class, 'verifyOtp']);
Route::post('/reset-password', [ForgotPasswordController::class, 'resetPassword']);
Route::post('/resend-otp', [ForgotPasswordController::class, 'resendOtp']);


Route::middleware(['auth:sanctum'])->group(function () {
    Route::group(['middleware' => ['doctor']], function () {
        // Checkup management
        Route::get('/doctor/checkup', [DoctorCheckupController::class, 'getCheckupData']);
        Route::post('/doctor/start-checkup', [DoctorCheckupController::class, 'startCheckup']);
        Route::post('/doctor/complete-checkup', [DoctorCheckupController::class, 'completeCheckup']);
        
        // Prescription management
        Route::get('/patient/{patientId}/prescriptions', [DoctorCheckupController::class, 'getPatientPrescriptions']);
        Route::post('/doctor/submit-prescription', [DoctorCheckupController::class, 'submitPrescription']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    Route::get('/age-distribution', [AgeDistributionController::class, 'getAgeDistribution']);
});


//-------------------------------------------------------------------//-----------------//------------------//
//-----------------E-COMMERCE BACKEND ROUTES-------------------------//


// Medical supply routes for patients
Route::middleware(['auth:sanctum'])->group(function () {
    // Medical supplies routes
    Route::get('/patient/supplies', [MedsProdController::class, 'getMedicalSuppliesForPatient']);
    Route::get('/patient/supplies/{id}', [MedsProdController::class, 'getMedicalSupplyForPatient']);
    Route::get('/patient/supplies/category/{category}', [MedsProdController::class, 'getSuppliesByCategoryForPatient']);
    Route::get('/patient/supplies/search', [MedsProdController::class, 'searchMedicalSuppliesForPatient']);
    Route::get('/patient/supplies/related/{id}', [MedsProdController::class, 'getRelatedSupplies']);
    Route::get('/patient/supplies/categories', [MedsProdController::class, 'getCategoriesForPatient']);
    Route::get('/patient/supplies/featured', [MedsProdController::class, 'getFeaturedSuppliesForPatient']);
    Route::get('/patient/supplies/low-stock', [MedsProdController::class, 'getLowStockSuppliesForPatient']);

});

// Public image serving route - should be outside auth middleware
Route::get('/medical-supply-image/{filename}', [MedsProdController::class, 'serveImage']);



// for the Staff and Admin to manage the products and reviews
Route::prefix('supplies')->group(function () {
    Route::get('/list', [SupplyController::class, 'getSupplies']);
    Route::get('/{id}', [SupplyController::class, 'getSupply']);
    Route::get('/low-stock/list', [SupplyController::class, 'getLowStockSupplies']);
    // Supply management routes
Route::post('/supplies/update/{id}', [SupplyController::class, 'updateSupply']);
Route::delete('/supplies/delete/{id}', [SupplyController::class, 'deleteSupply']);
});


// Patient Orders Routes
Route::get('/patient-orders', [PatientOrdersController::class, 'getAllOrders']);
Route::get('/patient-orders/grouped-by-date', [PatientOrdersController::class, 'getOrdersGroupedByDate']);
Route::get('/patient-orders/{orderID}', [PatientOrdersController::class, 'getOrderDetails']);
Route::put('/patient-orders/{orderID}/status', [PatientOrdersController::class, 'updateOrderStatus']);
Route::get('/patient-orders/statistics/overview', [PatientOrdersController::class, 'getOrdersStatistics']);
Route::get('/patient-orders/analytics/sales', [PatientOrdersController::class, 'getSalesAnalytics']);
Route::get('/patient-orders/reports/sales', [PatientOrdersController::class, 'downloadSalesReport']);
Route::get('/patient-orders/metrics/overview', [PatientOrdersController::class, 'getOrderMetrics']);
Route::get('/patient-orders/search/quick', [PatientOrdersController::class, 'searchOrders']);




// Protected routes (require authentication - staff/nurse only)
Route::middleware(['auth:sanctum'])->group(function () {
    

    
    Route::prefix('supplies')->group(function () {
        Route::post('/add', [MedicalSupplyController::class, 'addSupply']);
        Route::get('/', [MedicalSupplyController::class, 'getSupplies']);
        Route::get('/{id}', [MedicalSupplyController::class, 'getSupply']);
        Route::put('/update/{id}', [MedicalSupplyController::class, 'updateSupply']);
        Route::delete('/delete/{id}', [MedicalSupplyController::class, 'deleteSupply']);
        Route::patch('/quick-update/{id}', [MedicalSupplyController::class, 'quickUpdateSupply']);
        Route::get('/analytics/dashboard', [MedicalSupplyController::class, 'getAnalytics']);
        Route::get('/low-stock', [MedicalSupplyController::class, 'getLowStockSupplies']);
        
        // Patient routes
        Route::get('/patient/all', [MedicalSupplyController::class, 'getMedicalSuppliesForPatient']);
        Route::get('/patient/{id}', [MedicalSupplyController::class, 'getMedicalSupplyForPatient']);
        Route::get('/patient/category/{category}', [MedicalSupplyController::class, 'getSuppliesByCategoryForPatient']);
        Route::get('/patient/search/query', [MedicalSupplyController::class, 'searchMedicalSuppliesForPatient']);
        Route::get('/patient/related/{id}', [MedicalSupplyController::class, 'getRelatedSupplies']);
    });
//         // ✅ Patient orders routes - outside reviews prefix
// Route::middleware('auth:sanctum')->group(function () {
//     Route::get('/patient/orders', [OrderController::class, 'getPatientOrders']);
//     Route::get('/patient/orders/{orderID}', [OrderController::class, 'getOrderDetails']);
//     Route::post('/patient/orders/{orderID}/cancel', [OrderController::class, 'cancelOrder']);
// });

    
    // Reviews Management
    Route::prefix('reviews')->group(function () {
        Route::get('/all', [MedicalSupplyController::class, 'getAllReviews']);
        Route::patch('/{reviewID}/status', [MedicalSupplyController::class, 'updateReviewStatus']);
        Route::delete('/{reviewID}/delete', [MedicalSupplyController::class, 'deleteReview']);
        Route::get('/stats/dashboard', [MedicalSupplyController::class, 'getReviewStats']);
        Route::get('/product/{supplyID}', [MedicalSupplyController::class, 'getProductReviewsForStaff']);
    });
});

// Test route for backend connection
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Backend API is working!',
        'timestamp' => now(),
        'environment' => app()->environment()
    ]);
});

// Health check route
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'service' => 'E-commerce API']);
});

Route::middleware('auth:sanctum')->group(function () {
    // Medical supplies routes
    Route::get('/medical-supplies', [MedicalSupplyController::class, 'getMedicalSuppliesForPatient']);
    Route::get('/medical-supplies/{id}', [MedicalSupplyController::class, 'getMedicalSupplyForPatient']);
    Route::get('/medical-supplies/category/{category}', [MedicalSupplyController::class, 'getSuppliesByCategoryForPatient']);
    Route::get('/medical-supplies/search', [MedicalSupplyController::class, 'searchMedicalSuppliesForPatient']);
    Route::get('/medical-supplies/{id}/related', [MedicalSupplyController::class, 'getRelatedSupplies']);

// Public Product Reviews Routes
Route::get('/reviews/product/{supplyID}', [ProductReviewController::class, 'getProductReviews']);

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

// Protected routes (require authentication)
Route::middleware(['auth:sanctum'])->group(function () {
    
    // User route
    Route::get('/user', function () {
        $user = auth()->user();
        return response()->json([
            'success' => true,
            'user' => [
                'userID' => $user->userID,
                'first_name' => $user->first_name,
                'last_name' => $user->last_name,
                'email' => $user->email,
                'phone_number' => $user->phone_number,
                'userLevel' => $user->userLevel
            ]
        ]);
    });

    // Admin/Staff Medical Supplies Routes
    Route::prefix('supplies')->group(function () {
        Route::post('/add', [MedicalSupplyController::class, 'addSupply']);
        Route::post('/update/{id}', [MedicalSupplyController::class, 'updateSupply']);
        Route::post('/quick-update/{id}', [MedicalSupplyController::class, 'quickUpdateSupply']);
        Route::get('/analytics/dashboard', [MedicalSupplyController::class, 'getAnalytics']);
        // Supply management routes
Route::post('/supplies/update/{id}', [SupplyController::class, 'updateSupply']);
Route::delete('/supplies/delete/{id}', [SupplyController::class, 'deleteSupply']);
    });

    // Product Reviews Management (Staff/Admin)
    Route::prefix('user-product-reviews')->group(function () {
        Route::get('/', [UserProdReviewController::class, 'getProductReviews']);
        Route::get('/statistics', [UserProdReviewController::class, 'getReviewStatistics']);
        Route::put('/{id}/status', [UserProdReviewController::class, 'updateReviewStatus']);
        Route::delete('/{id}', [UserProdReviewController::class, 'deleteProductReview']);
    });

    // ============ CHECKOUT & PAYMENT ROUTES ============
    
// Test route for backend connection
Route::get('/test', function () {
    return response()->json([
        'success' => true,
        'message' => 'Backend API is working!',
        'timestamp' => now(),
        'environment' => app()->environment()
    ]);
});

    // Checkout routes
// Checkout routes
Route::post('/checkout/orders/create', [CheckoutModalController::class, 'createOrder']);
Route::get('/checkout/orders', [CheckoutModalController::class, 'getUserOrders']);
Route::get('/checkout/orders/{orderID}', [CheckoutModalController::class, 'getOrderDetails']);
Route::post('/checkout/orders/{orderID}/cancel', [CheckoutModalController::class, 'cancelOrder']);
Route::post('/checkout/orders/{orderID}/process-payment', [CheckoutModalController::class, 'processPayment']);
Route::post('/checkout/orders/{orderID}/verify-payment', [CheckoutModalController::class, 'verifyPayment']);
Route::post('/checkout/orders/{orderID}/create-payment-intent', [CheckoutModalController::class, 'createPaymentIntent']);
Route::post('/checkout/orders/{orderID}/process-payment-with-method', [CheckoutModalController::class, 'processPaymentWithMethod']);
Route::post('/checkout/create-payment-method', [CheckoutModalController::class, 'createPaymentMethod']);
Route::get('/checkout/available-pickup-dates', [CheckoutModalController::class, 'getAvailablePickupDates']);
});


//edit Product Details Modal
Route::middleware('api')->group(function () {
    Route::get('/products/{id}', [ProductDetailModalController::class, 'getProduct']);
    Route::put('/products/{id}', [ProductDetailModalController::class, 'updateProduct']);
    Route::delete('/products/{id}', [ProductDetailModalController::class, 'deleteProduct']);
    Route::post('/products/{id}/quick-update', [ProductDetailModalController::class, 'quickUpdate']);
});



    // Patient Routes
    Route::prefix('patient')->group(function () {
        // Medical Supplies for Patients
        Route::prefix('supplies')->group(function () {
            Route::get('/', [MedicalSupplyController::class, 'getMedicalSuppliesForPatient']);
            Route::get('/{id}', [MedicalSupplyController::class, 'getMedicalSupplyForPatient']);
            Route::get('/category/{category}', [MedicalSupplyController::class, 'getSuppliesByCategoryForPatient']);
            Route::get('/search', [MedicalSupplyController::class, 'searchMedicalSuppliesForPatient']);
        });

        // Product Reviews for Patients
        Route::post('/reviews/submit', [ProductReviewController::class, 'submitReview']);
        Route::get('/product/{supplyID}', [ProductReviewController::class, 'getProductReviews']);

        // Cart Routes
        Route::prefix('cart')->group(function () {
            Route::get('/', [CartController::class, 'getUserCart']);
            Route::post('/add', [CartController::class, 'addToCart']);
            Route::put('/update/{cartID}', [CartController::class, 'updateCartItem']);
            Route::delete('/remove/{cartID}', [CartController::class, 'removeFromCart']);
            Route::delete('/clear', [CartController::class, 'clearCart']);
            Route::get('/count', [CartController::class, 'getCartCount']);
            Route::post('/validate-checkout', [CartController::class, 'validateCartForCheckout']);
        });

        // Wishlist Routes
        Route::prefix('wishlist')->group(function () {
            Route::get('/', [WishlistController::class, 'getUserWishlist']);
            Route::post('/add', [WishlistController::class, 'addToWishlist']);
            Route::delete('/remove/{supplyID}', [WishlistController::class, 'removeFromWishlist']);
        });
    });
});

// Public Paymongo Webhook (no auth required)
Route::post('/paymongo/webhook', [PaymongoController::class, 'handleWebhook']);

// Fallback route for API health check
Route::get('/health', function () {
    return response()->json(['status' => 'OK', 'message' => 'API is running']);
});

