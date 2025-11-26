<?php
return [
    // Authentication routes
    'GET' => [
        '/' => [
            'action' => 'Auth@index',
            'middleware' => []
        ],
        '/login' => [
            'action' => 'Auth@login',
            'middleware' => []
        ],
        '/register' => [
            'action' => 'Auth@register',
            'middleware' => []
        ],
        '/logout' => [
            'action' => 'Auth@logout',
            'middleware' => ['auth']
        ],
        
        // Admin routes
        '/admin' => [
            'action' => 'Admin@dashboard',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/dashboard' => [
            'action' => 'Admin@dashboard',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/students' => [
            'action' => 'Admin@students',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/attendance' => [
            'action' => 'Admin@attendance',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/announcements' => [
            'action' => 'Admin@announcements',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/events' => [
            'action' => 'Admin@events',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/learning_activities' => [
            'action' => 'Admin@learningActivities',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/parents' => [
            'action' => 'Admin@parents',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/reports' => [
            'action' => 'Admin@reports',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/settings' => [
            'action' => 'Admin@settings',
            'middleware' => ['auth', 'admin']
        ],
        
        // Parent routes
        '/parent' => [
            'action' => 'Parent@dashboard',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/dashboard' => [
            'action' => 'Parent@dashboard',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/attendance' => [
            'action' => 'Parent@attendance',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/announcements' => [
            'action' => 'Parent@announcements',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/events' => [
            'action' => 'Parent@events',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/learning_activities' => [
            'action' => 'Parent@learningActivities',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/messages' => [
            'action' => 'Parent@messages',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/notifications' => [
            'action' => 'Parent@notifications',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/profile' => [
            'action' => 'Parent@profile',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/settings' => [
            'action' => 'Parent@settings',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/enroll' => [
            'action' => 'Parent@enroll',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/progress' => [
            'action' => 'Parent@progress',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/faqs' => [
            'action' => 'Parent@faqs',
            'middleware' => ['auth', 'parent']
        ]
    ],
    
    'POST' => [
        '/login' => [
            'action' => 'Auth@authenticate',
            'middleware' => []
        ],
        '/register' => [
            'action' => 'Auth@store',
            'middleware' => []
        ],
        
        // Admin POST routes
        '/admin/students/store' => [
            'action' => 'Admin@storeStudent',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/attendance/mark' => [
            'action' => 'Admin@markAttendance',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/announcements/store' => [
            'action' => 'Admin@storeAnnouncement',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/events/store' => [
            'action' => 'Admin@storeEvent',
            'middleware' => ['auth', 'admin']
        ],
        '/admin/learning_activities/store' => [
            'action' => 'Admin@storeLearningActivity',
            'middleware' => ['auth', 'admin']
        ],
        
        // Parent POST routes
        '/parent/enroll/store' => [
            'action' => 'Parent@storeEnrollment',
            'middleware' => ['auth', 'parent']
        ],
        '/parent/messages/send' => [
            'action' => 'Parent@sendMessage',
            'middleware' => ['auth', 'parent']
        ]
    ]
];
