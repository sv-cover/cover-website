<?php

namespace App\Service;

use App\Service\Authentication;
use App\Service\Database;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Menu
{
    private $identity;

    public function __construct(
        private Database $db,
        private Authentication $auth,
        private UrlGeneratorInterface $router,
    ) {
    }

    public function main()
    {
        $menu = [];

        $menu['activities'] = [
            'label' => __('Events'),
            'submenu' => [
                [
                    'url' => $this->router->generate('events.list'),
                    'label' => __('Calendar'),
                    'title' => __('Upcoming activities')
                ],
                [
                    'url' => $this->router->generate('photos'),
                    'label' => __('Photos'),
                    'title' => __('Photos of Cover\'s activities.')
                ]
            ]
        ];

        $menu['studie'] = [
            'label' => __('Education'),
            'submenu' => [
                ['url' => $this->router->generate('page.single', ['id' => 149]), 'label' => __('Degree Programmes')],
                ['url' => $this->router->generate('page.single', ['id' => 24]),  'label' => __('Alumni')],
                ['url' => $this->router->generate('page.single', ['id' => 27]),  'label' => __('Student info')],
                ['url' => $this->router->generate('page.single', ['id' => 118]), 'label' => __('Student representation')],
                ['url' => 'https://studysupport.svcover.nl/', 'target' => '_blank', 'label' => __('Exams & Summaries')],
                ['url' => $this->router->generate('slug', ['slug' => 'books']), 'label' => __('Book Store')],
                ['url' => 'https://tutoring.svcover.nl/', 'target' => '_blank', 'label' => __('Tutoring')]
            ]
        ];

        $menu['career'] = [
            'label' => __('Career'),
            'url' => $this->router->generate('career'),
        ];

        $menu['vereniging'] = [
            'label' => __('Association'),
            'submenu' => [
                [
                    'url' => $this->router->generate('committees.single', ['slug' => 'board']),
                    'label' => __('Board')
                ],
                [
                    'url' => $this->router->generate('boards.list'),
                    'label' => __('Former Boards')
                ],
                [
                    'url' => $this->router->generate('committees.list'),
                    'label' => __('Committees')
                ],
                [
                    'url' => $this->router->generate('societies.list'),
                    'label' => __('Societies')
                ],
                [
                    'url' => $this->router->generate('page.single', ['id' => 28]),
                    'label' => __('Sister Associations')
                ],
                [
                    'url' => $this->router->generate('page.single', ['id' => 215]),
                    'label' => __('History')
                ],
                [
                    'url' => $this->router->generate('page.single', ['id' => 18]),
                    'label' => __('Become a member/contributor')
                ],
                [
                    'url' => $this->router->generate('page.single', ['id' => 214]),
                    'label' => __('Information for companies')
                ],
                [
                    'label' => __('Well-being'),
                    'url' => $this->router->generate('page.single', ['id' => 213]),
                ],
            ]
        ];

        $menu['contact'] = [
            'label' => __('Contact'),
            'url' => $this->router->generate('slug', ['slug' => 'contact']),
        ];

        /*
        Show highlight in menu if menu_highlight is configured in settings. Example:
        {
            "label": "Introduction 2023",
            "url": "https://introcee.svcover.nl/"
        }
        */
        $highlight = \json_decode(
            $this->db->getModel('DataModelConfiguratie')->get_value('menu_highlight', '')
        );
        if (!empty($highlight) && !empty($highlight->label) && !empty($highlight->url))
            $menu['highlight'] = [
                'label' => $highlight->label,
                'url' => $highlight->url,
                'class' => $highlight->class ?? 'is-highlighted',
                'title' => $highlight->title ?? null,
                'target' => $highlight->target ?? null,
            ];

        // Filter out any empty menu items (I'm looking at you, admin menu!)
        $menu = array_filter($menu, function($item) {
            return isset($item['url']) || !empty($item['submenu']);
        });

        return $menu;
    }

    public function tools()
    {
        $identity = $this->auth->getIdentity();

        $menu = [];

        $menu['internal'] = [
            'label' => __(''),
            'items' => [
                [
                    'label' => __('Members'),
                    'url' => $this->router->generate('almanak'),
                    'icon' => [
                        'fa' => 'fas fa-users',
                        'color' => 'cover',
                    ],
                ],
                [
                    'label' => __('Polls'),
                    'url' => $this->router->generate('polls.list'),
                    'icon' => [
                        'fa' => 'fas fa-poll-h',
                        'color' => 'cover',
                    ],
                ],
                // [
                //     'label' => __('Sticker map'),
                //     'url' => $this->router->generate('stickers'),
                //     'icon' => [
                //         'fa' => 'fas fa-map-marked-alt',
                //         'color' => 'cover',
                //     ],
                // ],
            ]
        ];

        $menu['external'] = [
            'label' => __('Tools'),
            'items' => [
                [
                    'label' => __('Wiki'),
                    'url' => 'https://wiki.svcover.nl/',
                    'target' => '_blank',
                    'icon' => [
                        'fa' => 'fas fa-graduation-cap',
                        'color' => 'cover',
                    ],
                ],
                [
                    'label' => __('Documents & Templates'),
                    'url' => 'https://sd.svcover.nl/',
                    'target' => '_blank',
                    'icon' => [
                        'img' => '/images/applications/sd.png',
                    ],
                ],
                [
                    'label' => __('Merchandise'),
                    'url' => 'https://merchandise.svcover.nl/',
                    'target' => '_blank',
                    'icon' => [
                        'fa' => 'fas fa-tshirt',
                        'color' => 'cover',
                    ],
                ],
                [
                    'label' => __('Exams & Summaries'),
                    'url' => 'https://studysupport.svcover.nl/',
                    'target' => '_blank',
                    'icon' => [
                        'fa' => 'fas fa-book',
                        'color' => 'cover',
                    ],
                ],
                [
                    'label' => __('Tutoring'),
                    'url' => 'https://tutoring.svcover.nl/',
                    'target' => '_blank',
                    'icon' => [
                        'img' => '/images/applications/tutoring.svg',
                    ],
                ],
                [
                    'label' => __('Submit an Idea'),
                    'url' => 'https://idea.svcover.nl/',
                    'target' => '_blank',
                    'icon' => [
                        'img' => '/images/applications/idea.svg',
                    ],
                ],
            ]
        ];

        $menu['admin'] = [
            'label' => __('Committee'),
            'items' => []
        ];

        if ($identity->member_in_committee(COMMISSIE_BESTUUR) ||
            $identity->member_in_committee(COMMISSIE_KANDIBESTUUR) ||
            $identity->member_in_committee(COMMISSIE_EASY))
            $menu['admin']['label'] = __('Admin');

        if ($identity->member_in_committee()) { // Member in any committee at all
            $menu['external']['items'][] = [
                'icon' => [
                    'fa' => 'fas fa-print',
                    'color' => 'cover',
                ],
                'url' => 'https://myprint.svcover.nl/',
                'label' => __('Printer'),
                'target' => '_blank',
                'title' => __("Print documents on Cover's printer")
            ];

            $menu['external']['items'][] = [
                'icon' => [
                    'img' => '/images/applications/reclaim.svg',
                ],
                'url' => 'https://reclaim.svcover.nl/',
                'label' => __('Reclaim'),
                'target' => '_blank',
                'title' => __('Claim your expenses.')
            ];

            $menu['external']['items'][] = [
                'label' => __('Webmail'),
                'title' => __('Webmail for Cover email accounts.'),
                'url' => 'https://webmail.svcover.nl/',
                'target' => '_blank',
                'icon' => [
                    'img' => '/images/applications/mail.svg',
                ],
            ];

            $menu['admin']['items'][] = [
                'label' => __('Mailing lists'),
                'title' => __('Manage your committee\'s mailing lists.'),
                'url' => $this->router->generate('mailing_lists.list'),
                'icon' => [
                    'fa' => 'fas fa-mail-bulk',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
            ];

            $menu['admin']['items'][] = [
                'url' => $this->router->generate('sign_up_forms.list'),
                'label' => __('Forms'),
                'title' => __('Manage your committee\'s sign-up forms.'),
                'icon' => [
                    'fa' => 'fas fa-list-alt',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
            ];
        }

        if ($identity->member_in_committee(COMMISSIE_BESTUUR) ||
            $identity->member_in_committee(COMMISSIE_KANDIBESTUUR) ||
            $identity->member_in_committee(COMMISSIE_EASY)) {
            $menu['admin']['items'][] = [
                'icon' => [
                    'fa' => 'fas fa-file-alt',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
                'url' => $this->router->generate('page.list'),
                'label' => __('Pages'),
                'title' => __('View and manage pages.')
            ];

            $menu['admin']['items'][] = [
                'icon' => [
                    'fa' => 'fas fa-user-plus',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
                'url' => $this->router->generate('registrations.pending.list'),
                'label' => __('Pending registrations'),
                'title' => __('People who signed up for Cover, but did not yet confirm their email address.')
            ];
        }

        if ($identity->member_in_committee(COMMISSIE_BESTUUR) ||
            $identity->member_in_committee(COMMISSIE_KANDIBESTUUR)) {
            $menu['admin']['items'][] = [
                'label' => __('Active members'),
                'title' => __('All active committee members according to the website.'),
                'url' => $this->router->generate('committee_members'),
                'icon' => [
                    'fa' => 'fas fa-user-friends',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
            ];
            $menu['admin']['items'][] = [
                'icon' => [
                    'fa' => 'fas fa-building',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
                'url' => $this->router->generate('partners.list'),
                'label' => __('Partners'),
                'title' => __('All partner profiles and banners.')
            ];
        }


        if ($identity->member_in_committee(COMMISSIE_EASY)) {
            $menu['admin']['items'][] = [
                'label' => __('Device sessions'),
                'title' => __('Manage device sessions.'),
                'url' => $this->router->generate('device_sessions.list'),
                'icon' => [
                    'fa' => 'fas fa-desktop',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
            ];
            $menu['admin']['items'][] = [
                'label' => __('Settings'),
                'title' => __('Manage a few of the website’s settings.'),
                'url' => $this->router->generate('settings.list'),
                'icon' => [
                    'fa' => 'fas fa-cog',
                    'color' => 'dark',
                    'icon_color' => 'light'
                ],
            ];
        }

        // Filter out any empty menu items (I'm looking at you, admin menu!)
        $menu = array_filter($menu, function($item) {
            return !empty($item['items']);
        });

        return $menu;
    }
}
