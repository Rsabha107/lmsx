<?php

namespace App\Data;

class LmsData
{
    public static function all(): array
    {
        return [
            'event'   => 'Atlas Cup 2026',
            'today'   => 'Sat, 18 Apr 2026',
            'kpis'    => self::kpis(),
            'schedule'=> self::schedule(),
            'supervisorJobs' => self::supervisorJobs(),
            'checkpoints'    => self::checkpoints(),
            'mapNodes'       => self::mapNodes(),
            'liveJobs'       => self::liveJobs(),
            'contacts'       => self::contacts(),
            'notifications'  => self::notifications(),
            'audit'          => self::audit(),
        ];
    }

    public static function kpis(): array
    {
        return [
            ['id'=>'otar','label'=>'On-time arrival rate',      'value'=>'94.2%',    'delta'=>'+1.8 pts',   'tone'=>'ok',      'trend'=>[78,82,85,83,88,91,92,94,93,94,94,94]],
            ['id'=>'avgd','label'=>'Avg. delay',                'value'=>'6.4 min',  'delta'=>'-2.1 min',   'tone'=>'ok',      'trend'=>[14,12,11,10,9,9,8,7,8,7,6,6]],
            ['id'=>'jobs','label'=>'Jobs completed / planned',  'value'=>'142 / 156','delta'=>'91%',        'tone'=>'primary', 'trend'=>[40,55,70,85,100,112,118,125,132,138,140,142]],
            ['id'=>'chk', 'label'=>'Checkpoint compliance',     'value'=>'98.1%',    'delta'=>'+0.4 pts',   'tone'=>'ok',      'trend'=>[94,95,96,96,97,97,98,98,98,98,98,98]],
            ['id'=>'util','label'=>'Vehicle utilization',       'value'=>'76%',      'delta'=>'+3 pts',     'tone'=>'primary', 'trend'=>[50,55,60,62,65,68,70,72,73,74,75,76]],
            ['id'=>'drv', 'label'=>'Driver availability',       'value'=>'41 / 48',  'delta'=>'7 on leave', 'tone'=>'neutral', 'trend'=>[45,44,46,45,43,42,41,42,41,41,41,41]],
            ['id'=>'inc', 'label'=>'Active incidents',          'value'=>'3',        'delta'=>'1 critical', 'tone'=>'warn',    'trend'=>[1,0,2,1,3,2,4,3,3,2,3,3]],
            ['id'=>'sla', 'label'=>'SLA breaches (24h)',        'value'=>'2',        'delta'=>'-1 vs. yday','tone'=>'danger',  'trend'=>[5,4,4,3,3,2,3,2,2,2,2,2]],
        ];
    }

    public static function schedule(): array
    {
        return [
            [
                'id'=>'J-1042','team'=>'FC Meridian',   'kind'=>'arrival',  'from'=>'CDG T2',     'to'=>'Hotel Aurora',    'dep'=>'14:20','arr'=>'15:05','pax'=>28,'vehicle'=>'Coach 12','status'=>'in-progress','code'=>'MER','jobId'=>'JOB-2401','actual'=>'14:18','source'=>'live-AF812','checksComplete'=>3,'checksTotal'=>7,
                'supervisor'=>'P. Anand','driver'=>'K. Haddad',
                'checkpoints' => [
                    ['id'=>'CK1','name'=>'Vehicle dispatch',      'state'=>'done',    'at'=>'14:05','by'=>'System'],
                    ['id'=>'CK2','name'=>'Arrived at origin',     'state'=>'done',    'at'=>'14:18','by'=>'P. Anand (mobile)'],
                    ['id'=>'CK3','name'=>'Team on board',         'state'=>'done',    'at'=>'14:32','by'=>'P. Anand (mobile)'],
                    ['id'=>'CK4','name'=>'Bags loaded',           'state'=>'active',  'at'=>null,   'by'=>null],
                    ['id'=>'CK5','name'=>'Depart origin',         'state'=>'pending', 'at'=>null,   'by'=>null],
                    ['id'=>'CK6','name'=>'Arrive at destination', 'state'=>'pending', 'at'=>null,   'by'=>null],
                    ['id'=>'CK7','name'=>'Handoff complete',      'state'=>'pending', 'at'=>null,   'by'=>null],
                ]
            ],
            [
                'id'=>'J-1043','team'=>'Nordstad FK',   'kind'=>'arrival',  'from'=>'CDG T1',     'to'=>'Hotel Verdi',     'dep'=>'14:45','arr'=>'15:40','pax'=>32,'vehicle'=>'Coach 07','status'=>'scheduled',  'code'=>'NOR','jobId'=>'JOB-2402','source'=>'manual','checksComplete'=>0,'checksTotal'=>7,
                'supervisor'=>'H. Stein','driver'=>'F. Renaud',
                'checkpoints' => [
                    ['id'=>'CK1','name'=>'Vehicle dispatch',      'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK2','name'=>'Arrived at origin',     'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK3','name'=>'Team on board',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK4','name'=>'Bags loaded',           'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK5','name'=>'Depart origin',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK6','name'=>'Arrive at destination', 'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK7','name'=>'Handoff complete',      'state'=>'pending', 'at'=>null,'by'=>null],
                ]
            ],
            [
                'id'=>'J-1044','team'=>'Al-Sahra SC',   'kind'=>'transfer', 'from'=>'Hotel Aurora','to'=>'Stadium Azure',  'dep'=>'15:30','arr'=>'16:10','pax'=>24,'vehicle'=>'Coach 03','status'=>'scheduled',  'code'=>'SAH','source'=>'manual','checksComplete'=>0,'checksTotal'=>5,
                'supervisor'=>'S. Park','driver'=>'L. Martelli',
                'checkpoints' => [
                    ['id'=>'CK1','name'=>'Vehicle dispatch',      'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK2','name'=>'Arrived at origin',     'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK3','name'=>'Team on board',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK4','name'=>'Depart origin',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK5','name'=>'Arrive at destination', 'state'=>'pending', 'at'=>null,'by'=>null],
                ]
            ],
            [
                'id'=>'J-1045','team'=>'Club Pampas',   'kind'=>'arrival',  'from'=>'ORY',        'to'=>'Hotel Solene',    'dep'=>'15:50','arr'=>'16:55','pax'=>30,'vehicle'=>'Coach 14','status'=>'delayed',    'code'=>'PAM','delay'=>18,'jobId'=>'JOB-2403','source'=>'live-SK205','checksComplete'=>1,'checksTotal'=>7,
                'supervisor'=>'G. Morales','driver'=>'M. Okafor',
                'checkpoints' => [
                    ['id'=>'CK1','name'=>'Vehicle dispatch',      'state'=>'done',    'at'=>'15:40','by'=>'System'],
                    ['id'=>'CK2','name'=>'Arrived at origin',     'state'=>'active',  'at'=>null,   'by'=>null],
                    ['id'=>'CK3','name'=>'Team on board',         'state'=>'pending', 'at'=>null,   'by'=>null],
                    ['id'=>'CK4','name'=>'Bags loaded',           'state'=>'pending', 'at'=>null,   'by'=>null],
                    ['id'=>'CK5','name'=>'Depart origin',         'state'=>'pending', 'at'=>null,   'by'=>null],
                    ['id'=>'CK6','name'=>'Arrive at destination', 'state'=>'pending', 'at'=>null,   'by'=>null],
                    ['id'=>'CK7','name'=>'Handoff complete',      'state'=>'pending', 'at'=>null,   'by'=>null],
                ]
            ],
            [
                'id'=>'J-1046','team'=>'Tokai United',  'kind'=>'transfer', 'from'=>'Hotel Verdi','to'=>'Training Ground B','dep'=>'16:10','arr'=>'16:35','pax'=>22,'vehicle'=>'Van 22', 'status'=>'scheduled',  'code'=>'TOK','jobId'=>'JOB-2404','actual'=>'16:12','source'=>'manual','checksComplete'=>5,'checksTotal'=>5,
                'supervisor'=>'J. Lindqvist','driver'=>'E. Bauer',
                'checkpoints' => [
                    ['id'=>'CK1','name'=>'Vehicle dispatch',      'state'=>'done',    'at'=>'16:00','by'=>'System'],
                    ['id'=>'CK2','name'=>'Arrived at origin',     'state'=>'done',    'at'=>'16:08','by'=>'J. Lindqvist (mobile)'],
                    ['id'=>'CK3','name'=>'Team on board',         'state'=>'done',    'at'=>'16:12','by'=>'J. Lindqvist (mobile)'],
                    ['id'=>'CK4','name'=>'Depart origin',         'state'=>'done',    'at'=>'16:14','by'=>'J. Lindqvist (mobile)'],
                    ['id'=>'CK5','name'=>'Arrive at destination', 'state'=>'done',    'at'=>'16:33','by'=>'J. Lindqvist (mobile)'],
                ]
            ],
            [
                'id'=>'J-1047','team'=>'Officials Crew','kind'=>'transfer', 'from'=>'Hotel Ministry','to'=>'Stadium Azure','dep'=>'16:40','arr'=>'17:05','pax'=>9, 'vehicle'=>'Van 04', 'status'=>'scheduled',  'code'=>'OFF','source'=>'manual','checksComplete'=>0,'checksTotal'=>5,
                'supervisor'=>'R. Chen','driver'=>'T. Silva',
                'checkpoints' => [
                    ['id'=>'CK1','name'=>'Vehicle dispatch',      'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK2','name'=>'Arrived at origin',     'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK3','name'=>'Team on board',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK4','name'=>'Depart origin',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK5','name'=>'Arrive at destination', 'state'=>'pending', 'at'=>null,'by'=>null],
                ]
            ],
            [
                'id'=>'J-1048','team'=>'FC Meridian',   'kind'=>'departure','from'=>'Stadium Azure','to'=>'Hotel Aurora',  'dep'=>'22:30','arr'=>'23:05','pax'=>28,'vehicle'=>'Coach 12','status'=>'scheduled',  'code'=>'MER','source'=>'manual','checksComplete'=>0,'checksTotal'=>6,
                'supervisor'=>'P. Anand','driver'=>'K. Haddad',
                'checkpoints' => [
                    ['id'=>'CK1','name'=>'Vehicle dispatch',      'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK2','name'=>'Arrived at origin',     'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK3','name'=>'Team on board',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK4','name'=>'Bags loaded',           'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK5','name'=>'Depart origin',         'state'=>'pending', 'at'=>null,'by'=>null],
                    ['id'=>'CK6','name'=>'Arrive at destination', 'state'=>'pending', 'at'=>null,'by'=>null],
                ]
            ],
        ];
    }

    public static function supervisorJobs(): array
    {
        return [
            ['id'=>'J-1042','team'=>'FC Meridian','route'=>'CDG T2 → Hotel Aurora',   'window'=>'14:20 – 15:05','status'=>'in-progress','next'=>'Bags loaded','pax'=>28],
            ['id'=>'J-1045','team'=>'Club Pampas','route'=>'ORY → Hotel Solene',      'window'=>'15:50 – 16:55','status'=>'delayed',    'next'=>'Pickup',     'pax'=>30,'delay'=>18],
            ['id'=>'J-1047','team'=>'Officials',  'route'=>'Hotel Ministry → Stadium','window'=>'16:40 – 17:05','status'=>'scheduled',  'next'=>'Standby',    'pax'=>9],
            ['id'=>'J-1051','team'=>'Media Pool', 'route'=>'Hotel Verdi → Stadium',   'window'=>'17:30 – 18:00','status'=>'scheduled',  'next'=>'Standby',    'pax'=>14],
        ];
    }

    public static function checkpoints(): array
    {
        return [
            ['id'=>'c1','label'=>'Vehicle dispatch',  'time'=>'13:40','actual'=>'13:38','status'=>'done'],
            ['id'=>'c2','label'=>'Arrived at airport','time'=>'14:15','actual'=>'14:18','status'=>'done'],
            ['id'=>'c3','label'=>'Team on board',     'time'=>'14:35','actual'=>'14:32','status'=>'done'],
            ['id'=>'c4','label'=>'Bags loaded',       'time'=>'14:40','actual'=>null,   'status'=>'active'],
            ['id'=>'c5','label'=>'Depart airport',    'time'=>'14:45','actual'=>null,   'status'=>'pending'],
            ['id'=>'c6','label'=>'Arrive at hotel',   'time'=>'15:05','actual'=>null,   'status'=>'pending'],
            ['id'=>'c7','label'=>'Handoff complete',  'time'=>'15:10','actual'=>null,   'status'=>'pending'],
        ];
    }

    public static function mapNodes(): array
    {
        return [
            ['id'=>'cdg','label'=>'CDG',           'type'=>'airport','x'=>0.12,'y'=>0.28],
            ['id'=>'ory','label'=>'ORY',           'type'=>'airport','x'=>0.15,'y'=>0.78],
            ['id'=>'aur','label'=>'Hotel Aurora',  'type'=>'hotel',  'x'=>0.44,'y'=>0.30],
            ['id'=>'ver','label'=>'Hotel Verdi',   'type'=>'hotel',  'x'=>0.40,'y'=>0.60],
            ['id'=>'sol','label'=>'Hotel Solene',  'type'=>'hotel',  'x'=>0.48,'y'=>0.82],
            ['id'=>'min','label'=>'Hotel Ministry','type'=>'hotel',  'x'=>0.62,'y'=>0.46],
            ['id'=>'azr','label'=>'Stadium Azure', 'type'=>'stadium','x'=>0.82,'y'=>0.40],
            ['id'=>'trg','label'=>'Training B',    'type'=>'stadium','x'=>0.76,'y'=>0.72],
        ];
    }

    public static function liveJobs(): array
    {
        return [
            ['id'=>'J-1042','code'=>'MER','from'=>'cdg','to'=>'aur','progress'=>0.68,'status'=>'in-progress'],
            ['id'=>'J-1044','code'=>'SAH','from'=>'aur','to'=>'azr','progress'=>0.35,'status'=>'in-progress'],
            ['id'=>'J-1045','code'=>'PAM','from'=>'ory','to'=>'sol','progress'=>0.22,'status'=>'delayed'],
            ['id'=>'J-1046','code'=>'TOK','from'=>'ver','to'=>'trg','progress'=>0.80,'status'=>'in-progress'],
        ];
    }

    public static function contacts(): array
    {
        return [
            ['name'=>'Irena Volkov',    'role'=>'Logistics Manager',       'org'=>'Atlas Cup HQ',  'phone'=>'+33 6 12 44 00 01','on'=>true],
            ['name'=>'Marcus Idowu',    'role'=>'Dispatch Lead',           'org'=>'Atlas Cup HQ',  'phone'=>'+33 6 12 44 00 02','on'=>true],
            ['name'=>'Priya Anand',     'role'=>'Field Supervisor · CDG',  'org'=>'Ops Team A',    'phone'=>'+33 6 12 44 00 03','on'=>true],
            ['name'=>'Jonas Lindqvist', 'role'=>'Field Supervisor · ORY',  'org'=>'Ops Team B',    'phone'=>'+33 6 12 44 00 04','on'=>false],
            ['name'=>'Sofía Reyes',     'role'=>'Transport Partner',       'org'=>'Nord Coaches',  'phone'=>'+33 6 12 44 00 05','on'=>true],
            ['name'=>'Thabo Ngwenya',   'role'=>'Team Liaison · MER',      'org'=>'FC Meridian',   'phone'=>'+33 6 12 44 00 06','on'=>true],
            ['name'=>'Hannah Stein',    'role'=>'Team Liaison · NOR',      'org'=>'Nordstad FK',   'phone'=>'+33 6 12 44 00 07','on'=>true],
            ['name'=>'Omar El-Haddad',  'role'=>'Venue Ops',               'org'=>'Stadium Azure', 'phone'=>'+33 6 12 44 00 08','on'=>true],
        ];
    }

    public static function notifications(): array
    {
        return [
            ['id'=>'n1','t'=>'2 min ago', 'tone'=>'warn',   'title'=>'Flight AF812 delayed by 18 min',   'body'=>'J-1045 pickup window pushed to 15:50. Pampas liaison notified.'],
            ['id'=>'n2','t'=>'8 min ago', 'tone'=>'primary','title'=>'Checkpoint: team on board',          'body'=>'J-1042 · FC Meridian boarded Coach 12 at 14:32.'],
            ['id'=>'n3','t'=>'14 min ago','tone'=>'ok',     'title'=>'Daily summary emailed',              'body'=>'Morning snapshot sent to 23 recipients (exec + partner groups).'],
            ['id'=>'n4','t'=>'22 min ago','tone'=>'danger', 'title'=>'SLA breach — J-1039',                'body'=>'Officials transfer missed 15-min handoff. Audit log created.'],
            ['id'=>'n5','t'=>'41 min ago','tone'=>'neutral','title'=>'Driver roster updated',              'body'=>'S. Reyes moved from Van 22 to Coach 07.'],
        ];
    }

    public static function audit(): array
    {
        return [
            ['t'=>'15:22','who'=>'M. Idowu',    'role'=>'Dispatcher', 'action'=>'Reassigned',        'target'=>'Coach 07 → J-1043',         'meta'=>'reason: driver swap'],
            ['t'=>'15:18','who'=>'System',       'role'=>'Automation', 'action'=>'Flagged',           'target'=>'J-1045',                    'meta'=>'delay > 15 min'],
            ['t'=>'15:10','who'=>'P. Anand',     'role'=>'Supervisor', 'action'=>'Checkpoint ✓',      'target'=>'J-1042 · Team on board',    'meta'=>'signed, on-device'],
            ['t'=>'15:02','who'=>'I. Volkov',    'role'=>'Manager',    'action'=>'Approved',          'target'=>'Schedule revision v3',      'meta'=>'+4 transfers'],
            ['t'=>'14:47','who'=>'System',       'role'=>'Automation', 'action'=>'Notification sent', 'target'=>'MS Teams · #ops-live',      'meta'=>'J-1042 dispatched'],
            ['t'=>'14:31','who'=>'J. Lindqvist', 'role'=>'Supervisor', 'action'=>'Note added',        'target'=>'J-1045',                    'meta'=>'"awaiting gate release"'],
            ['t'=>'14:12','who'=>'S. Reyes',     'role'=>'Transport',  'action'=>'Vehicle handover',  'target'=>'Coach 12',                  'meta'=>'fuel 92%, clean'],
        ];
    }
}
