<?php

declare(strict_types=1);

namespace App\Domains\Identity\Catalog;

final class AccessCatalog
{
    /**
     * @return array<string, array{module: string, name: string}>
     */
    public static function permissions(): array
    {
        return [
            'platform.dashboard.view' => ['module' => 'platform', 'name' => 'View platform dashboard'],
            'platform.tenants.view' => ['module' => 'platform', 'name' => 'View tenants'],
            'platform.tenants.manage' => ['module' => 'platform', 'name' => 'Manage tenants'],
            'platform.plans.manage' => ['module' => 'platform', 'name' => 'Manage plans'],
            'platform.subscriptions.manage' => ['module' => 'platform', 'name' => 'Manage subscriptions'],
            'platform.announcements.manage' => ['module' => 'platform', 'name' => 'Manage announcements'],
            'platform.audit.view' => ['module' => 'platform', 'name' => 'View audit logs'],
            'platform.support.grant' => ['module' => 'platform', 'name' => 'Grant support access'],
            'platform.users.manage' => ['module' => 'platform', 'name' => 'Manage platform users'],
            'platform.health.view' => ['module' => 'platform', 'name' => 'View system health'],
            'organization.view' => ['module' => 'organization', 'name' => 'View organization'],
            'organization.manage' => ['module' => 'organization', 'name' => 'Manage organization'],
            'branches.manage' => ['module' => 'organization', 'name' => 'Manage branches'],
            'departments.manage' => ['module' => 'organization', 'name' => 'Manage departments'],
            'clients.view' => ['module' => 'clients', 'name' => 'View clients'],
            'clients.manage' => ['module' => 'clients', 'name' => 'Manage clients'],
            'contracts.manage' => ['module' => 'clients', 'name' => 'Manage contracts'],
            'sites.view' => ['module' => 'sites', 'name' => 'View sites'],
            'sites.manage' => ['module' => 'sites', 'name' => 'Manage sites'],
            'workforce.view' => ['module' => 'workforce', 'name' => 'View workforce'],
            'workforce.manage' => ['module' => 'workforce', 'name' => 'Manage workforce'],
            'workforce.documents.view' => ['module' => 'workforce', 'name' => 'View employee documents'],
            'workforce.documents.manage' => ['module' => 'workforce', 'name' => 'Manage employee documents'],
            'scheduling.view' => ['module' => 'scheduling', 'name' => 'View schedules'],
            'scheduling.manage' => ['module' => 'scheduling', 'name' => 'Manage schedules'],
            'attendance.view' => ['module' => 'attendance', 'name' => 'View attendance'],
            'attendance.punch' => ['module' => 'attendance', 'name' => 'Record own attendance'],
            'attendance.verify' => ['module' => 'attendance', 'name' => 'Verify attendance'],
            'attendance.correct' => ['module' => 'attendance', 'name' => 'Request attendance corrections'],
            'patrol.view' => ['module' => 'patrol', 'name' => 'View patrols'],
            'patrol.manage' => ['module' => 'patrol', 'name' => 'Manage patrols'],
            'patrol.complete' => ['module' => 'patrol', 'name' => 'Complete patrol checkpoints'],
            'inspections.view' => ['module' => 'inspections', 'name' => 'View inspections'],
            'inspections.manage' => ['module' => 'inspections', 'name' => 'Manage inspections'],
            'incidents.view' => ['module' => 'incidents', 'name' => 'View incidents'],
            'incidents.manage' => ['module' => 'incidents', 'name' => 'Manage incidents'],
            'incidents.report' => ['module' => 'incidents', 'name' => 'Report incidents'],
            'emergency.view' => ['module' => 'emergency', 'name' => 'View emergencies'],
            'emergency.raise' => ['module' => 'emergency', 'name' => 'Raise SOS'],
            'emergency.respond' => ['module' => 'emergency', 'name' => 'Respond to SOS'],
            'leave.view' => ['module' => 'leave', 'name' => 'View leave'],
            'leave.request' => ['module' => 'leave', 'name' => 'Request leave'],
            'leave.approve' => ['module' => 'leave', 'name' => 'Approve leave'],
            'equipment.manage' => ['module' => 'equipment', 'name' => 'Manage equipment'],
            'finance.view' => ['module' => 'finance', 'name' => 'View finance'],
            'finance.manage' => ['module' => 'finance', 'name' => 'Manage finance'],
            'compliance.view' => ['module' => 'compliance', 'name' => 'View compliance'],
            'compliance.manage' => ['module' => 'compliance', 'name' => 'Manage compliance'],
            'reports.view' => ['module' => 'reports', 'name' => 'View reports'],
            'reports.export' => ['module' => 'reports', 'name' => 'Export reports'],
            'settings.manage' => ['module' => 'settings', 'name' => 'Manage settings'],
            'team.manage' => ['module' => 'settings', 'name' => 'Manage team access'],
            'notifications.view' => ['module' => 'notifications', 'name' => 'View notifications'],
            'client.dashboard.view' => ['module' => 'client', 'name' => 'View client dashboard'],
            'client.sites.view' => ['module' => 'client', 'name' => 'View authorized sites'],
            'client.attendance.view' => ['module' => 'client', 'name' => 'View site attendance'],
            'client.patrols.view' => ['module' => 'client', 'name' => 'View patrol reports'],
            'client.incidents.view' => ['module' => 'client', 'name' => 'View incidents'],
            'client.requests.manage' => ['module' => 'client', 'name' => 'Manage service requests'],
            'client.contracts.view' => ['module' => 'client', 'name' => 'View contracts'],
            'client.invoices.view' => ['module' => 'client', 'name' => 'View invoices'],
            'client.reports.view' => ['module' => 'client', 'name' => 'View client reports'],
        ];
    }

    /**
     * @return array<string, array{portal: string, name: string, permissions: list<string>}>
     */
    public static function systemRoles(): array
    {
        $tenantAll = array_values(array_filter(array_keys(self::permissions()), fn (string $code) => ! str_starts_with($code, 'platform.') && ! str_starts_with($code, 'client.')));
        $viewOnly = array_values(array_filter($tenantAll, fn (string $code) => str_ends_with($code, '.view') || $code === 'reports.export' || $code === 'notifications.view'));
        $clientAll = array_values(array_filter(array_keys(self::permissions()), fn (string $code) => str_starts_with($code, 'client.') || $code === 'notifications.view'));
        $clientView = array_values(array_filter($clientAll, fn (string $code) => str_ends_with($code, '.view') || $code === 'notifications.view'));

        return [
            'platform_super_admin' => ['portal' => 'platform', 'name' => 'Platform Super Admin', 'permissions' => array_values(array_filter(array_keys(self::permissions()), fn (string $code) => str_starts_with($code, 'platform.')))],
            'platform_admin' => ['portal' => 'platform', 'name' => 'Platform Admin', 'permissions' => array_values(array_filter(array_keys(self::permissions()), fn (string $code) => str_starts_with($code, 'platform.') && $code !== 'platform.users.manage'))],
            'support_operator' => ['portal' => 'platform', 'name' => 'Support Operator', 'permissions' => ['platform.dashboard.view', 'platform.tenants.view', 'platform.support.grant', 'platform.audit.view', 'platform.health.view']],
            'organization_owner' => ['portal' => 'tenant', 'name' => 'Organization Owner', 'permissions' => $tenantAll],
            'tenant_admin' => ['portal' => 'tenant', 'name' => 'Tenant Admin', 'permissions' => $tenantAll],
            'operations_manager' => ['portal' => 'tenant', 'name' => 'Operations Manager', 'permissions' => ['organization.view', 'sites.view', 'sites.manage', 'clients.view', 'workforce.view', 'scheduling.view', 'scheduling.manage', 'attendance.view', 'attendance.verify', 'patrol.view', 'patrol.manage', 'inspections.view', 'inspections.manage', 'incidents.view', 'incidents.manage', 'emergency.view', 'emergency.respond', 'reports.view', 'reports.export', 'notifications.view']],
            'hr_manager' => ['portal' => 'tenant', 'name' => 'HR Manager', 'permissions' => ['workforce.view', 'workforce.manage', 'workforce.documents.view', 'workforce.documents.manage', 'leave.view', 'leave.approve', 'attendance.view', 'reports.view', 'notifications.view']],
            'site_manager' => ['portal' => 'tenant', 'name' => 'Site Manager', 'permissions' => ['sites.view', 'sites.manage', 'scheduling.view', 'scheduling.manage', 'attendance.view', 'attendance.verify', 'patrol.view', 'patrol.manage', 'inspections.view', 'inspections.manage', 'incidents.view', 'incidents.manage', 'emergency.view', 'emergency.respond', 'workforce.view', 'notifications.view', 'reports.view']],
            'supervisor' => ['portal' => 'supervisor', 'name' => 'Supervisor', 'permissions' => ['sites.view', 'scheduling.view', 'attendance.view', 'attendance.verify', 'patrol.view', 'inspections.view', 'inspections.manage', 'incidents.view', 'incidents.manage', 'emergency.view', 'emergency.respond', 'workforce.view', 'reports.view', 'notifications.view', 'leave.view']],
            'guard' => ['portal' => 'guard', 'name' => 'Guard', 'permissions' => ['attendance.punch', 'attendance.view', 'attendance.correct', 'patrol.view', 'patrol.complete', 'incidents.report', 'emergency.raise', 'leave.request', 'leave.view', 'notifications.view', 'sites.view']],
            'finance_manager' => ['portal' => 'tenant', 'name' => 'Finance Manager', 'permissions' => ['finance.view', 'finance.manage', 'clients.view', 'reports.view', 'reports.export', 'workforce.view', 'notifications.view']],
            'auditor' => ['portal' => 'tenant', 'name' => 'Auditor', 'permissions' => $viewOnly],
            'client_admin' => ['portal' => 'client', 'name' => 'Client Admin', 'permissions' => $clientAll],
            'client_manager' => ['portal' => 'client', 'name' => 'Client Manager', 'permissions' => array_values(array_filter($clientAll, fn (string $code) => $code !== 'client.invoices.view'))],
            'client_viewer' => ['portal' => 'client', 'name' => 'Client Viewer', 'permissions' => $clientView],
        ];
    }

    /**
     * @return array<string, array{name: string, module: string}>
     */
    public static function features(): array
    {
        return [
            'module.clients' => ['name' => 'Client management', 'module' => 'clients'],
            'module.sites' => ['name' => 'Sites', 'module' => 'sites'],
            'module.workforce' => ['name' => 'Workforce', 'module' => 'workforce'],
            'module.scheduling' => ['name' => 'Scheduling', 'module' => 'scheduling'],
            'module.attendance' => ['name' => 'Attendance', 'module' => 'attendance'],
            'module.patrol' => ['name' => 'Patrol', 'module' => 'patrol'],
            'module.inspections' => ['name' => 'Inspections', 'module' => 'inspections'],
            'module.incidents' => ['name' => 'Incidents', 'module' => 'incidents'],
            'module.emergency' => ['name' => 'Emergency', 'module' => 'emergency'],
            'module.leave' => ['name' => 'Leave', 'module' => 'leave'],
            'module.equipment' => ['name' => 'Equipment', 'module' => 'equipment'],
            'module.finance' => ['name' => 'Finance', 'module' => 'finance'],
            'module.compliance' => ['name' => 'Compliance', 'module' => 'compliance'],
            'module.reports' => ['name' => 'Reports', 'module' => 'reports'],
            'module.client_portal' => ['name' => 'Client portal', 'module' => 'client'],
        ];
    }

    /**
     * @return array<string, list<string>>
     */
    public static function planFeatures(): array
    {
        $all = array_keys(self::features());

        return [
            'starter' => ['module.clients', 'module.sites', 'module.workforce', 'module.scheduling', 'module.attendance', 'module.leave'],
            'professional' => ['module.clients', 'module.sites', 'module.workforce', 'module.scheduling', 'module.attendance', 'module.leave', 'module.patrol', 'module.incidents', 'module.emergency', 'module.equipment', 'module.reports', 'module.client_portal'],
            'enterprise' => $all,
        ];
    }

    /**
     * @return list<string>
     */
    public static function supportViewPermissions(): array
    {
        return [
            'organization.view', 'sites.view', 'clients.view', 'workforce.view', 'scheduling.view',
            'attendance.view', 'patrol.view', 'inspections.view', 'incidents.view', 'emergency.view',
            'reports.view',
        ];
    }
}
