<?php

echo json_encode([
    'status' => 'success',
    'base_roles' => getPowertechBaseRoleDefinitions(),
    'permissions' => getPowertechPermissionDefinitions(),
    'admin_menus' => getPowertechAdminMenuMap(),
    'companies' => [
        ['code' => 'PT4', 'label' => 'Powertech 2004'],
        ['code' => 'PTA', 'label' => 'Powertech Engine Assembly'],
        ['code' => 'PTE', 'label' => 'Powertech Energy Solutions'],
    ],
]);
