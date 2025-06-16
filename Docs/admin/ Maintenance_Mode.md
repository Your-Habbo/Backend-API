// Check maintenance status (public endpoint)
GET /api/maintenance/status
Response: {
    "maintenance": {
        "is_maintenance_mode": false,
        "title": "YourHabbo Under Maintenance",
        "message": "We are currently performing...",
        "scheduled_start": null,
        "scheduled_end": null,
        "estimated_duration": null,
        "contact_email": "support@yourhabbo.codeneko.co",
        "social_links": {
            "discord": "https://discord.gg/yourhabbo"
        }
    }
}

// Check if current user should see maintenance mode
GET /api/maintenance/check
Response: {
    "maintenance": {
        "is_active": false,
        "can_bypass": false,
        "should_show_maintenance": false,
        "title": "YourHabbo Under Maintenance",
        "message": "We are currently performing...",
        "estimated_duration": null,
        "contact_email": "support@yourhabbo.codeneko.co",
        "social_links": {
            "discord": "https://discord.gg/yourhabbo"
        },
        "scheduled_end": null
    }
}


// Admin: Enable maintenance mode immediately
POST /api/admin/maintenance/enable
{
    "title": "Emergency Maintenance",
    "message": "We are fixing a critical issue. Back soon!",
    "duration": 30
}

// Admin: Schedule maintenance mode
POST /api/admin/maintenance/schedule
{
    "scheduled_start": "2025-06-16T02:00:00Z",
    "scheduled_end": "2025-06-16T04:00:00Z",
    "title": "Scheduled Maintenance",
    "message": "Weekly maintenance window"
}

// Admin: Disable maintenance mode
POST /api/admin/maintenance/disable

// Admin: Update maintenance settings
PUT /api/admin/maintenance
{
    "title": "Updated title",
    "message": "Updated message",
    "allowed_ips": ["192.168.1.100"],
    "allowed_roles": ["admin"],
    "contact_email": "support@yourhabbo.codeneko.co"
}
*/