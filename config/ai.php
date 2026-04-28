<?php

return [
    'scopes'    =>  [
        'global' => [
            //  Dont do this here because it makes infinite loop with user object.
            '\NextDeveloper\IAM\Database\Scopes\AuthorizationScope',
            '\NextDeveloper\Commons\Database\GlobalScopes\LimitScope',
        ],
        'iam_accounts' => [
            //'\NextDeveloper\IAM\Database\Scopes\AuthorizationScope'
        ]
    ],
];
