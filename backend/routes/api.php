<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\DirectoryController;
use App\Http\Controllers\Api\V1\OnboardingController;
use App\Http\Controllers\Api\V1\OperationsController;
use App\Http\Controllers\Api\V1\PlatformController;
use App\Http\Controllers\Api\V1\PublicController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->group(function () {
    Route::get('health', [PublicController::class, 'health']);
    Route::get('public/plans', [PublicController::class, 'plans']);
    Route::post('public/contact', [PublicController::class, 'contact'])->middleware('throttle:10,1');

    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/mfa', [AuthController::class, 'verifyMfa'])->middleware('throttle:login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgotPassword'])->middleware('throttle:6,1');
    Route::post('auth/reset-password', [AuthController::class, 'resetPassword'])->middleware('throttle:6,1');
    Route::post('auth/invitations/accept', [AuthController::class, 'acceptInvitation']);
    Route::post('onboarding/register', [OnboardingController::class, 'register'])->middleware('throttle:10,1');

    Route::middleware(['auth:sanctum', 'context', 'mfa'])->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::patch('profile', [AuthController::class, 'updateProfile']);
        Route::post('auth/mfa/enroll', [AuthController::class, 'enrollMfa']);
        Route::post('auth/mfa/confirm', [AuthController::class, 'confirmMfa']);

        Route::prefix('onboarding')->group(function () {
            Route::get('/', [OnboardingController::class, 'show']);
            Route::post('business-type', [OnboardingController::class, 'businessType']);
            Route::post('organization', [OnboardingController::class, 'organization']);
            Route::post('verify', [OnboardingController::class, 'verify']);
            Route::post('resend', [OnboardingController::class, 'resend']);
            Route::post('profile', [OnboardingController::class, 'profile']);
            Route::post('branch', [OnboardingController::class, 'branch']);
            Route::post('invites', [OnboardingController::class, 'invites']);
            Route::post('workforce-settings', [OnboardingController::class, 'workforceSettings']);
            Route::post('plan', [OnboardingController::class, 'plan']);
            Route::post('complete', [OnboardingController::class, 'complete']);
        });

        Route::prefix('platform')->group(function () {
            Route::get('dashboard', [PlatformController::class, 'dashboard']);
            Route::get('tenants', [PlatformController::class, 'tenants']);
            Route::post('tenants', [PlatformController::class, 'storeTenant']);
            Route::post('tenants/{tenant}/status', [PlatformController::class, 'setTenantStatus']);
            Route::get('plans', [PlatformController::class, 'plans']);
            Route::get('subscriptions', [PlatformController::class, 'subscriptions']);
            Route::post('tenants/{tenant}/subscription', [PlatformController::class, 'changePlan']);
            Route::match(['get', 'post'], 'announcements', [PlatformController::class, 'announcements']);
            Route::get('audit', [PlatformController::class, 'audit']);
            Route::match(['get', 'post'], 'grants', [PlatformController::class, 'grants']);
            Route::post('grants/{grant}/revoke', [PlatformController::class, 'revokeGrant']);
            Route::get('health', [PlatformController::class, 'health']);
            Route::match(['get', 'post'], 'users', [PlatformController::class, 'platformUsers']);
            Route::get('tenants/{tenant}/support/sites', [PlatformController::class, 'supportSites'])->middleware('support.grant');
        });

        Route::middleware('tenant.active')->group(function () {
            Route::get('dashboard', [DirectoryController::class, 'dashboard']);
            Route::match(['get', 'post'], 'branches', [DirectoryController::class, 'branches']);
            Route::match(['get', 'post'], 'departments', [DirectoryController::class, 'departments']);
            Route::match(['get', 'post'], 'clients', [DirectoryController::class, 'clients'])->middleware('feature:module.clients');
            Route::post('clients/{client}/contacts', [DirectoryController::class, 'clientContact'])->middleware('feature:module.clients');
            Route::match(['get', 'post'], 'contracts', [DirectoryController::class, 'contracts'])->middleware('feature:module.clients');
            Route::match(['get', 'post'], 'sites', [DirectoryController::class, 'sites'])->middleware('feature:module.sites');
            Route::match(['get', 'post'], 'employees', [DirectoryController::class, 'employees'])->middleware('feature:module.workforce');
            Route::post('employees/{employee}/sites', [DirectoryController::class, 'assignSupervisorSites']);
            Route::post('employees/{employee}/documents', [DirectoryController::class, 'uploadDocument']);
            Route::get('documents/{document}/download', [DirectoryController::class, 'downloadDocument']);
            Route::get('me/documents', [DirectoryController::class, 'myDocuments']);
            Route::post('invitations', [DirectoryController::class, 'invite']);
            Route::match(['get', 'patch'], 'settings', [DirectoryController::class, 'settings']);

            Route::match(['get', 'post'], 'shift-templates', [OperationsController::class, 'templates'])->middleware('feature:module.scheduling');
            Route::match(['get', 'post'], 'rosters', [OperationsController::class, 'rosters'])->middleware('feature:module.scheduling');
            Route::post('rosters/{roster}/publish', [OperationsController::class, 'publishRoster'])->middleware('feature:module.scheduling');
            Route::match(['get', 'post'], 'shifts', [OperationsController::class, 'shifts'])->middleware('feature:module.scheduling');
            Route::post('shifts/{shift}/assignments', [OperationsController::class, 'assign'])->middleware('feature:module.scheduling');
            Route::post('assignments/{assignment}/replace', [OperationsController::class, 'replace'])->middleware('feature:module.scheduling');
            Route::get('me/shifts', [OperationsController::class, 'myShift'])->middleware('feature:module.scheduling');

            Route::post('attendance/check-in', [OperationsController::class, 'checkIn'])->middleware(['feature:module.attendance', 'throttle:30,1']);
            Route::post('attendance/check-out', [OperationsController::class, 'checkOut'])->middleware(['feature:module.attendance', 'throttle:30,1']);
            Route::get('attendance', [OperationsController::class, 'attendance'])->middleware('feature:module.attendance');
            Route::post('attendance/{attendance}/verify', [OperationsController::class, 'verifyAttendance'])->middleware('feature:module.attendance');
            Route::post('attendance/{attendance}/corrections', [OperationsController::class, 'correct'])->middleware('feature:module.attendance');
            Route::post('corrections/{correction}/review', [OperationsController::class, 'reviewCorrection'])->middleware('feature:module.attendance');

            Route::match(['get', 'post'], 'patrol-plans', [OperationsController::class, 'patrolPlans'])->middleware('feature:module.patrol');
            Route::post('patrol-plans/{plan}/checkpoints', [OperationsController::class, 'checkpoint'])->middleware('feature:module.patrol');
            Route::match(['get', 'post'], 'patrol-runs', [OperationsController::class, 'patrolRuns'])->middleware('feature:module.patrol');
            Route::post('patrol-runs/{run}/events', [OperationsController::class, 'patrolEvent'])->middleware('feature:module.patrol');

            Route::match(['get', 'post'], 'inspection-templates', [OperationsController::class, 'inspectionTemplates'])->middleware('feature:module.inspections');
            Route::match(['get', 'post'], 'inspections', [OperationsController::class, 'inspections'])->middleware('feature:module.inspections');
            Route::post('inspections/{inspection}/complete', [OperationsController::class, 'completeInspection'])->middleware('feature:module.inspections');

            Route::get('incident-categories', [OperationsController::class, 'categories'])->middleware('feature:module.incidents');
            Route::match(['get', 'post'], 'incidents', [OperationsController::class, 'incidents'])->middleware('feature:module.incidents');
            Route::post('incidents/{incident}/status', [OperationsController::class, 'incidentStatus'])->middleware('feature:module.incidents');

            Route::match(['get', 'post'], 'emergency/sos', [OperationsController::class, 'sos'])->middleware(['feature:module.emergency', 'throttle:sos']);
            Route::post('emergency/sos/{alert}/transition', [OperationsController::class, 'sosTransition'])->middleware('feature:module.emergency');

            Route::get('leave-types', [OperationsController::class, 'leaveTypes'])->middleware('feature:module.leave');
            Route::match(['get', 'post'], 'leave', [OperationsController::class, 'leave'])->middleware('feature:module.leave');
            Route::post('leave/{leave}/review', [OperationsController::class, 'reviewLeave'])->middleware('feature:module.leave');

            Route::match(['get', 'post'], 'equipment', [OperationsController::class, 'equipment'])->middleware('feature:module.equipment');
            Route::post('equipment/{equipment}/allocate', [OperationsController::class, 'allocateEquipment'])->middleware('feature:module.equipment');
            Route::post('equipment/{equipment}/return', [OperationsController::class, 'returnEquipment'])->middleware('feature:module.equipment');

            Route::match(['get', 'post'], 'invoices', [OperationsController::class, 'invoices'])->middleware('feature:module.finance');
            Route::post('payments', [OperationsController::class, 'payment'])->middleware('feature:module.finance');
            Route::post('expenses', [OperationsController::class, 'expense'])->middleware('feature:module.finance');
            Route::match(['get', 'post'], 'payslips', [OperationsController::class, 'payslips'])->middleware('feature:module.finance');
            Route::post('payslips/{payslip}/approve', [OperationsController::class, 'approvePayslip'])->middleware('feature:module.finance');

            Route::get('notifications', [OperationsController::class, 'notifications']);
            Route::post('notifications/{notification}/read', [OperationsController::class, 'readNotification']);
            Route::get('reports/{type}', [OperationsController::class, 'report'])->middleware('feature:module.reports');
            Route::match(['get', 'post'], 'compliance', [OperationsController::class, 'compliance'])->middleware('feature:module.compliance');
            Route::match(['get', 'post'], 'service-requests', [OperationsController::class, 'serviceRequests'])->middleware('feature:module.client_portal');
        });
    });
});
