<?php
/**
 * StudyMe AI Platform — Comprehensive SEO Engine & Metadata Helper
 * 
 * Provides automated metadata generation, dynamic title/description resolution,
 * Schema.org JSON-LD structured data (EducationalOrganization, WebSite, Course, BreadcrumbList, FAQPage),
 * Open Graph, Twitter/X cards, canonical URLs, and robots directives.
 */

if (!function_exists('get_base_url')) {
    /**
     * Retrieve the root base URL for StudyMe dynamically
     */
    function get_base_url() {
        if (defined('APP_URL') && !empty(APP_URL)) {
            return rtrim(APP_URL, '/');
        }
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? "https://" : "http://";
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $scriptDir = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        
        // Find StudyMe base root
        $pos = strpos($scriptDir, '/StudyMe');
        if ($pos !== false) {
            $basePath = substr($scriptDir, 0, $pos + 8);
        } else {
            $basePath = rtrim($scriptDir, '/');
        }
        return rtrim($protocol . $host . $basePath, '/');
    }
}

if (!function_exists('get_canonical_url')) {
    /**
     * Generate a strict canonical URL
     */
    function get_canonical_url($customPath = null) {
        $baseUrl = get_base_url();
        if ($customPath !== null) {
            return $baseUrl . '/' . ltrim($customPath, '/');
        }
        $reqUri = strtok($_SERVER['REQUEST_URI'] ?? '', '?');
        if (empty($reqUri) || $reqUri === '/' || strpos($reqUri, 'index.php') !== false) {
            return $baseUrl . '/index.php';
        }
        // Normalize subpath
        $pos = strpos($reqUri, '/StudyMe');
        if ($pos !== false) {
            $reqUri = substr($reqUri, $pos + 8);
        }
        return $baseUrl . '/' . ltrim($reqUri, '/');
    }
}

if (!function_exists('get_default_page_seo')) {
    /**
     * Retrieve tailored default metadata based on the current executing script
     */
    function get_default_page_seo() {
        $scriptName = str_replace('\\', '/', ($_SERVER['SCRIPT_NAME'] ?? '') . ' ' . ($_SERVER['REQUEST_URI'] ?? '') . ' ' . ($_SERVER['PHP_SELF'] ?? ''));
        $baseUrl    = get_base_url();

        // 1. Private / Dashboard / Auth Areas
        if (
            strpos($scriptName, '/admin/') !== false ||
            strpos($scriptName, '/teacher/') !== false ||
            strpos($scriptName, '/student/') !== false ||
            strpos($scriptName, '/payments/') !== false ||
            strpos($scriptName, '/auth/') !== false ||
            strpos($scriptName, 'login.php') !== false ||
            strpos($scriptName, 'register.php') !== false ||
            strpos($scriptName, 'certificates/view.php') !== false
        ) {
            return [
                'title'       => 'Portal | StudyMe AI Learning',
                'description' => 'Secure StudyMe student, instructor and administrative portal.',
                'keywords'    => 'StudyMe portal, dashboard, account',
                'is_private'  => true,
                'noindex'     => true
            ];
        }

        // 2. Public Hubs and Pages
        if (strpos($scriptName, 'ai-learning.php') !== false) {
            return [
                'title'       => 'AI Learning & 24/7 AI Tutor | StudyMe',
                'description' => 'Experience interactive 24/7 AI tutoring on StudyMe. Master difficult topics, practice exam questions, debug code, and receive instant personalized explanations.',
                'keywords'    => 'AI learning platform, AI tutor, AI study assistant, personalized learning, interactive AI lessons, StudyMe AI, 24/7 tutor',
                'canonical'   => $baseUrl . '/ai-learning.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'AI Learning', 'url' => $baseUrl . '/ai-learning.php']
                ],
                'faq' => [
                    ['q' => 'What can StudyMe AI do for students?', 'a' => 'StudyMe AI provides 24/7 personalized tutoring, explains complex academic concepts step-by-step, generates practice quizzes, debugs code, and adapts to each student’s unique pace.'],
                    ['q' => 'Can StudyMe AI help with WAEC, NECO and JAMB preparation?', 'a' => 'Yes. The AI tutor provides step-by-step breakdowns of verified past questions and syllabus topics across Mathematics, Physics, Chemistry, Biology, English, and Commercial subjects.'],
                    ['q' => 'Is the AI Tutor included with courses?', 'a' => 'Yes, active learners have continuous access to the AI study assistant across all enrolled courses and subject modules.']
                ]
            ];
        }

        if (strpos($scriptName, 'courses/index.php') !== false) {
            return [
                'title'       => 'Courses | StudyMe — AI-Powered Learning Platform',
                'description' => 'Browse all StudyMe courses across Technology Bootcamps, University Modules, Secondary School (WAEC, NECO, JAMB), and Teacher Programs with 24/7 AI tutoring.',
                'keywords'    => 'online courses, technology courses, university courses, secondary school learning, WAEC prep, NECO prep, JAMB prep, AI-powered learning',
                'canonical'   => $baseUrl . '/courses/index.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Courses', 'url' => $baseUrl . '/courses/index.php']
                ],
                'faq' => [
                    ['q' => 'What courses does StudyMe offer?', 'a' => 'StudyMe offers practical Technology Bootcamps (Web Dev, Cybersecurity, Python, AI), University Undergraduate Modules, and Secondary School Exam Preparation (WAEC, NECO, JAMB).'],
                    ['q' => 'How does enrollment work?', 'a' => 'Simply choose a course, sign up or log in, and begin learning immediately with curriculum modules, video lessons, and interactive AI tutor support.'],
                    ['q' => 'Do StudyMe courses include certificates?', 'a' => 'Yes, upon completing all lessons and passing milestone quizzes, students earn verifiable digital certificates with official verification IDs.']
                ]
            ];
        }

        if (strpos($scriptName, 'courses/technology.php') !== false) {
            return [
                'title'       => 'Technology Courses & Bootcamps | StudyMe',
                'description' => 'Master in-demand digital and coding skills with StudyMe. Practical bootcamps in Full-Stack Web Development, Cybersecurity, Python, AI Tools, and UI/UX Design.',
                'keywords'    => 'technology courses, web development course, cybersecurity course, programming courses, Python bootcamp, digital skills, AI learning, StudyMe tech',
                'canonical'   => $baseUrl . '/courses/technology.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Courses', 'url' => $baseUrl . '/courses/index.php'],
                    ['name' => 'Technology', 'url' => $baseUrl . '/courses/technology.php']
                ],
                'faq' => [
                    ['q' => 'Are technology bootcamps suitable for beginners?', 'a' => 'Yes, our courses range from Beginner to Advanced, featuring guided hands-on projects and AI code assistance.'],
                    ['q' => 'Will I build real-world projects?', 'a' => 'Every technology course includes practical portfolio projects, assignments, and real-world implementation exercises.']
                ]
            ];
        }

        if (strpos($scriptName, 'courses/university.php') !== false) {
            return [
                'title'       => 'University Courses & Modules | StudyMe',
                'description' => 'Undergraduate university curriculum modules in Computer Science, Mathematics, Physics, Chemistry, Economics, and Accounting with lecture notes and 24/7 AI tutoring.',
                'keywords'    => 'university courses, undergraduate modules, computer science degree courses, engineering mathematics, general physics, economics, accounting, online degree prep',
                'canonical'   => $baseUrl . '/courses/university.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Courses', 'url' => $baseUrl . '/courses/index.php'],
                    ['name' => 'University', 'url' => $baseUrl . '/courses/university.php']
                ],
                'faq' => [
                    ['q' => 'Which university faculties are covered?', 'a' => 'StudyMe covers Sciences, Engineering, Computing, Social Sciences, and Management Sciences with standard university credit-level modules.'],
                    ['q' => 'Can StudyMe AI explain complex derivations and theorems?', 'a' => 'Yes, our AI Tutor is calibrated to walk students through mathematical derivations, proofs, and theoretical formulas step-by-step.']
                ]
            ];
        }

        if (strpos($scriptName, 'courses/secondary.php') !== false) {
            return [
                'title'       => 'Secondary School, WAEC, NECO & JAMB | StudyMe',
                'description' => 'Comprehensive senior secondary school subjects and exam preparation for WAEC, NECO, and JAMB. Syllabi, verified past questions, and step-by-step AI solutions.',
                'keywords'    => 'secondary school learning, WAEC preparation, NECO preparation, JAMB preparation, past questions, senior secondary syllabus, SSCE online prep, UTME practice',
                'canonical'   => $baseUrl . '/courses/secondary.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Courses', 'url' => $baseUrl . '/courses/index.php'],
                    ['name' => 'Secondary School', 'url' => $baseUrl . '/courses/secondary.php']
                ],
                'faq' => [
                    ['q' => 'Does StudyMe support WAEC, NECO, and JAMB?', 'a' => 'Yes, we provide full syllabus coverage, topic notes, practice quizzes, and authentic past question solutions for WAEC, NECO, and JAMB UTME.'],
                    ['q' => 'Are past questions updated?', 'a' => 'Our database includes recent exam years with verified answer keys and detailed AI-assisted step-by-step explanations.']
                ]
            ];
        }

        if (strpos($scriptName, 'courses/teacher.php') !== false || strpos($scriptName, 'teachers.php') !== false) {
            return [
                'title'       => 'Teacher Program & Instructor Suite | StudyMe',
                'description' => 'Join StudyMe as a verified educator. Build modern digital curriculums, deliver video lessons, create auto-graded quizzes, and track student analytics with an AI co-pilot.',
                'keywords'    => 'teacher learning platform, become an instructor, teach online, educator portal, teach secondary school, teach technology courses, StudyMe teachers',
                'canonical'   => $baseUrl . '/teachers.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Teachers', 'url' => $baseUrl . '/teachers.php']
                ],
                'faq' => [
                    ['q' => 'Who can apply to become an instructor on StudyMe?', 'a' => 'Certified educators, university lecturers, and industry professionals in Technology, Sciences, and Arts can register and apply for verified instructor status.'],
                    ['q' => 'How does the AI co-pilot assist teachers?', 'a' => 'The AI co-pilot assists educators with quiz generation, syllabus structuring, and real-time student performance analytics.']
                ]
            ];
        }

        if (strpos($scriptName, 'courses/past-questions.php') !== false) {
            return [
                'title'       => 'Past Questions & Exam Prep | StudyMe',
                'description' => 'Practice authentic WAEC, NECO, and JAMB past questions with instant grading, detailed explanations, and 24/7 AI tutor guidance.',
                'keywords'    => 'past questions, WAEC past questions, NECO past questions, JAMB CBT practice, exam preparation, past questions and answers, StudyMe exam prep',
                'canonical'   => $baseUrl . '/courses/past-questions.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Courses', 'url' => $baseUrl . '/courses/index.php'],
                    ['name' => 'Past Questions', 'url' => $baseUrl . '/courses/past-questions.php']
                ]
            ];
        }

        if (strpos($scriptName, 'about.php') !== false) {
            return [
                'title'       => 'About StudyMe | AI-Powered Learning Platform',
                'description' => 'Learn about StudyMe’s mission to transform global education through accessible 24/7 AI tutoring, certified instructors, and accredited digital courses.',
                'keywords'    => 'about StudyMe, AI education company, digital learning platform, edtech mission, online education innovation',
                'canonical'   => $baseUrl . '/about.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'About', 'url' => $baseUrl . '/about.php']
                ]
            ];
        }

        if (strpos($scriptName, 'pricing.php') !== false) {
            return [
                'title'       => 'Pricing & Plans | StudyMe',
                'description' => 'Explore transparent and affordable pricing for StudyMe courses, technology bootcamps, secondary school exam prep, and instructor licenses.',
                'keywords'    => 'StudyMe pricing, course fees, online education cost, affordable tech bootcamps, WAEC prep cost, teacher license price',
                'canonical'   => $baseUrl . '/pricing.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Pricing', 'url' => $baseUrl . '/pricing.php']
                ],
                'faq' => [
                    ['q' => 'How much do courses cost on StudyMe?', 'a' => 'Course rates are structured affordably per track: Technology bootcamps (₦10,000), Secondary school subjects (₦1,000), University modules (₦1,500), and Instructor licenses (₦5,000).'],
                    ['q' => 'Are there any hidden recurring fees?', 'a' => 'No hidden fees. Once enrolled, students have access to the course content and AI tutoring for that track.']
                ]
            ];
        }

        if (strpos($scriptName, 'contact.php') !== false) {
            return [
                'title'       => 'Contact StudyMe | Support & Academic Inquiries',
                'description' => 'Have questions or need assistance? Contact the StudyMe academic and technical support team for help with courses, enrollments, and partnerships.',
                'keywords'    => 'contact StudyMe, customer support, academic help, technical assistance, StudyMe email, student support',
                'canonical'   => $baseUrl . '/contact.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Contact', 'url' => $baseUrl . '/contact.php']
                ]
            ];
        }

        if (strpos($scriptName, 'careers.php') !== false) {
            return [
                'title'       => 'Careers & Opportunities | StudyMe',
                'description' => 'Join the StudyMe team and help build the future of AI-powered education. Explore open roles in engineering, instruction, curriculum design, and operations.',
                'keywords'    => 'StudyMe careers, edtech jobs, educational technology hiring, remote education roles, AI curriculum jobs',
                'canonical'   => $baseUrl . '/careers.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Careers', 'url' => $baseUrl . '/careers.php']
                ]
            ];
        }

        if (strpos($scriptName, 'community.php') !== false) {
            return [
                'title'       => 'Student Community & Study Groups | StudyMe',
                'description' => 'Connect with thousands of fellow students on StudyMe. Join study groups, collaborate on technical projects, share notes, and learn together.',
                'keywords'    => 'student community, study groups, peer learning, online study community, coding peer groups, exam revision circle',
                'canonical'   => $baseUrl . '/community.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Community', 'url' => $baseUrl . '/community.php']
                ]
            ];
        }

        if (strpos($scriptName, 'faq.php') !== false) {
            return [
                'title'       => 'Frequently Asked Questions (FAQ) | StudyMe',
                'description' => 'Get answers to frequently asked questions about StudyMe courses, 24/7 AI tutoring, enrollments, exams (WAEC/NECO/JAMB), payments, and certificates.',
                'keywords'    => 'StudyMe FAQ, online learning questions, AI tutor FAQ, course enrollment help, certificate verification questions',
                'canonical'   => $baseUrl . '/faq.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'FAQ', 'url' => $baseUrl . '/faq.php']
                ]
            ];
        }

        if (strpos($scriptName, 'help.php') !== false) {
            return [
                'title'       => 'Help Center & Documentation | StudyMe',
                'description' => 'Find step-by-step guides, tutorials, and troubleshooting articles to navigate your StudyMe learning journey, AI tutor features, and dashboard.',
                'keywords'    => 'StudyMe help center, knowledge base, student guide, learning tutorials, troubleshooting',
                'canonical'   => $baseUrl . '/help.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Help Center', 'url' => $baseUrl . '/help.php']
                ]
            ];
        }

        if (strpos($scriptName, 'certificates/verify.php') !== false) {
            return [
                'title'       => 'Verify Certificate Authenticity | StudyMe',
                'description' => 'Verify the authenticity of credentials, diplomas, and course completion certificates issued by the StudyMe AI Learning Platform.',
                'keywords'    => 'verify certificate, certificate verification, StudyMe credentials, check certificate ID, digital credential verify',
                'canonical'   => $baseUrl . '/certificates/verify.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Verify Certificate', 'url' => $baseUrl . '/certificates/verify.php']
                ]
            ];
        }

        if (strpos($scriptName, 'privacy.php') !== false) {
            return [
                'title'       => 'Privacy Policy | StudyMe',
                'description' => 'Learn how StudyMe collects, protects, and handles your personal data, learning records, and privacy with strict security standards.',
                'keywords'    => 'StudyMe privacy policy, data protection, student data privacy',
                'canonical'   => $baseUrl . '/privacy.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Privacy Policy', 'url' => $baseUrl . '/privacy.php']
                ]
            ];
        }

        if (strpos($scriptName, 'terms.php') !== false) {
            return [
                'title'       => 'Terms of Service | StudyMe',
                'description' => 'Review the terms and conditions governing the use of StudyMe platform, courses, AI tools, and student subscriptions.',
                'keywords'    => 'StudyMe terms of service, user agreement, course terms',
                'canonical'   => $baseUrl . '/terms.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Terms of Service', 'url' => $baseUrl . '/terms.php']
                ]
            ];
        }

        if (strpos($scriptName, 'cookies.php') !== false) {
            return [
                'title'       => 'Cookie Policy | StudyMe',
                'description' => 'Understand how StudyMe uses cookies and related technologies to provide personalized learning experiences and maintain secure sessions.',
                'keywords'    => 'StudyMe cookie policy, cookies usage, session cookies',
                'canonical'   => $baseUrl . '/cookies.php',
                'breadcrumbs' => [
                    ['name' => 'Home', 'url' => $baseUrl . '/index.php'],
                    ['name' => 'Cookie Policy', 'url' => $baseUrl . '/cookies.php']
                ]
            ];
        }

        if (strpos($scriptName, '404.php') !== false) {
            return [
                'title'       => 'Page Not Found (404) | StudyMe',
                'description' => 'The requested page or learning resource could not be found. Explore our courses, AI learning portal, or help center.',
                'keywords'    => '404, not found, StudyMe',
                'is_private'  => true,
                'noindex'     => true
            ];
        }

        // Default Homepage Fallback
        return [
            'title'       => 'StudyMe — AI-Powered Learning Platform',
            'description' => 'StudyMe is an AI-powered learning platform that helps students learn, practice, prepare for exams (WAEC, NECO, JAMB), and develop in-demand technology skills with personalized 24/7 AI tutoring.',
            'keywords'    => 'AI learning platform, AI-powered learning platform, online learning platform, online courses, technology courses, web development course, cybersecurity course, programming courses, university courses, secondary school learning, WAEC preparation, NECO preparation, JAMB preparation, past questions, online education, digital skills, teacher learning platform, AI tutor, AI study assistant, online study platform, StudyMe',
            'canonical'   => $baseUrl . '/index.php',
            'breadcrumbs' => []
        ];
    }
}

if (!function_exists('render_seo_head')) {
    /**
     * Renders all SEO meta tags, Open Graph, Twitter Cards, and Structured Data
     *
     * @param array $options Configuration array for page SEO metadata
     */
    function render_seo_head($options = []) {
        $defaults = get_default_page_seo();
        
        // Merge explicit global helper variables if set in page scope
        global $pageTitle, $page_title, $page_desc, $page_description, $page_keywords, $course, $courseData, $breadcrumbs, $faqData, $is_private, $noindex, $canonical_url;
        
        $explicitTitle = $options['title'] ?? $pageTitle ?? $page_title ?? $defaults['title'];
        $explicitDesc  = $options['description'] ?? $page_desc ?? $page_description ?? $defaults['description'];
        $explicitKw    = $options['keywords'] ?? $page_keywords ?? $defaults['keywords'];
        $explicitCanon = $options['canonical'] ?? $canonical_url ?? $defaults['canonical'] ?? get_canonical_url();
        $explicitImage = $options['image'] ?? get_base_url() . '/assets/img/og-preview.png';
        $explicitType  = $options['type'] ?? 'website';
        $isPrivate     = !empty($options['is_private']) || !empty($options['noindex']) || !empty($is_private) || !empty($noindex) || !empty($defaults['is_private']);
        $bCrumbs       = !empty($options['breadcrumbs']) ? $options['breadcrumbs'] : (!empty($breadcrumbs) ? $breadcrumbs : ($defaults['breadcrumbs'] ?? []));
        $cData         = !empty($options['course']) ? $options['course'] : (!empty($course) ? $course : (!empty($courseData) ? $courseData : null));
        $fData         = !empty($options['faq']) ? $options['faq'] : (!empty($faqData) ? $faqData : ($defaults['faq'] ?? []));
        $extraSchema   = !empty($options['schema']) ? $options['schema'] : null;

        // Clean & format title
        $title = trim($explicitTitle);
        if ($title !== 'StudyMe — AI-Powered Learning Platform' && strpos($title, 'StudyMe') === false) {
            $title .= ' | StudyMe';
        }

        $description = trim($explicitDesc);
        $keywords    = trim($explicitKw);
        $canonical   = trim($explicitCanon);
        $image       = trim($explicitImage);
        $baseUrl     = get_base_url();

        ?>
    <!-- SEO Primary Metadata -->
    <title><?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
    <meta name="description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="keywords" content="<?= htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="author" content="StudyMe AI Platform">
    <link rel="canonical" href="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">

    <?php if ($isPrivate): ?>
    <!-- Search Engine Indexing Directives (Private Area) -->
    <meta name="robots" content="noindex, nofollow, noarchive, nosnippet">
    <meta name="googlebot" content="noindex, nofollow">
    <?php else: ?>
    <!-- Search Engine Indexing Directives (Public Area) -->
    <meta name="robots" content="index, follow, max-snippet:-1, max-image-preview:large, max-video-preview:-1">
    <meta name="googlebot" content="index, follow">
    <?php endif; ?>

    <!-- Open Graph / Facebook Protocol -->
    <meta property="og:locale" content="en_US">
    <meta property="og:type" content="<?= htmlspecialchars($explicitType, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:site_name" content="StudyMe">
    <meta property="og:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:url" content="<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image" content="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>">
    <meta property="og:image:alt" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Twitter / X Card Metadata -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:site" content="@studyme_ai">
    <meta name="twitter:creator" content="@studyme_ai">
    <meta name="twitter:title" content="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:description" content="<?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?>">
    <meta name="twitter:image" content="<?= htmlspecialchars($image, ENT_QUOTES, 'UTF-8') ?>">

    <!-- Schema.org JSON-LD Structured Data Graph -->
    <script type="application/ld+json">
    {
        "@context": "https://schema.org",
        "@graph": [
            {
                "@type": "EducationalOrganization",
                "@id": "<?= $baseUrl ?>/#organization",
                "name": "StudyMe",
                "url": "<?= $baseUrl ?>",
                "logo": "<?= $baseUrl ?>/assets/img/logo.png",
                "description": "StudyMe is an AI-powered learning platform offering practical technology bootcamps, university undergraduate courses, secondary school WAEC/NECO/JAMB exam prep, and 24/7 AI tutor guidance.",
                "sameAs": [
                    "https://facebook.com/studymehq",
                    "https://twitter.com/studyme_ai",
                    "https://linkedin.com/company/studyme-ai",
                    "https://instagram.com/studymehq",
                    "https://youtube.com/@studyme-ai"
                ],
                "contactPoint": {
                    "@type": "ContactPoint",
                    "contactType": "customer support",
                    "email": "support@studyme.online",
                    "availableLanguage": ["English"]
                }
            },
            {
                "@type": "WebSite",
                "@id": "<?= $baseUrl ?>/#website",
                "url": "<?= $baseUrl ?>",
                "name": "StudyMe — AI-Powered Learning Platform",
                "publisher": { "@id": "<?= $baseUrl ?>/#organization" },
                "potentialAction": {
                    "@type": "SearchAction",
                    "target": "<?= $baseUrl ?>/courses/index.php?search={search_term_string}",
                    "query-input": "required name=search_term_string"
                }
            }
            <?php if (!empty($bCrumbs)): ?>,
            {
                "@type": "BreadcrumbList",
                "itemListElement": [
                    <?php 
                    $bList = [];
                    foreach ($bCrumbs as $bPos => $bItem) {
                        $bList[] = json_encode([
                            '@type' => 'ListItem',
                            'position' => $bPos + 1,
                            'name' => $bItem['name'],
                            'item' => $bItem['url']
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                    echo implode(',', $bList);
                    ?>
                ]
            }
            <?php endif; ?>
            <?php if ($cData): ?>,
            {
                "@type": "Course",
                "@id": "<?= htmlspecialchars($canonical, ENT_QUOTES, 'UTF-8') ?>#course",
                "name": <?= json_encode($cData['title'] ?? '') ?>,
                "description": <?= json_encode($cData['short_description'] ?? $cData['description'] ?? $description) ?>,
                "provider": {
                    "@id": "<?= $baseUrl ?>/#organization"
                },
                "offers": {
                    "@type": "Offer",
                    "category": "Paid",
                    "price": "<?= number_format((float)($cData['price'] ?? 0), 2, '.', '') ?>",
                    "priceCurrency": "NGN",
                    "availability": "https://schema.org/InStock",
                    "validFrom": "<?= date('Y-m-d') ?>"
                },
                "hasCourseInstance": {
                    "@type": "CourseInstance",
                    "courseMode": "Online",
                    "courseWorkload": "PT<?= max(1, (int)(($cData['duration_minutes'] ?? 1800) / 60)) ?>H"
                }
                <?php if (!empty($cData['teacher_name'])): ?>,
                "instructor": {
                    "@type": "Person",
                    "name": <?= json_encode($cData['teacher_name']) ?>
                }
                <?php endif; ?>
            }
            <?php endif; ?>
            <?php if (!empty($fData)): ?>,
            {
                "@type": "FAQPage",
                "mainEntity": [
                    <?php 
                    $fList = [];
                    foreach ($fData as $faqItem) {
                        $fList[] = json_encode([
                            '@type' => 'Question',
                            'name' => $faqItem['q'],
                            'acceptedAnswer' => [
                                '@type' => 'Answer',
                                'text' => $faqItem['a']
                            ]
                        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                    }
                    echo implode(',', $fList);
                    ?>
                ]
            }
            <?php endif; ?>
            <?php if ($extraSchema): ?>,
            <?= is_array($extraSchema) ? json_encode($extraSchema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : $extraSchema ?>
            <?php endif; ?>
        ]
    }
    </script>
        <?php
    }
}

if (!function_exists('render_seo_breadcrumbs')) {
    /**
     * Render semantic, accessible HTML breadcrumbs
     * 
     * @param array $breadcrumbs Array of items: [['name' => 'Home', 'url' => '...'], ...]
     */
    function render_seo_breadcrumbs($breadcrumbs = []) {
        if (empty($breadcrumbs)) {
            return;
        }
        ?>
        <nav aria-label="breadcrumb" class="mb-4">
            <ol class="breadcrumb small">
                <?php 
                $count = count($breadcrumbs);
                foreach ($breadcrumbs as $index => $item): 
                    $isLast = ($index === $count - 1);
                ?>
                    <?php if ($isLast): ?>
                        <li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?></li>
                    <?php else: ?>
                        <li class="breadcrumb-item">
                            <a href="<?= htmlspecialchars($item['url'], ENT_QUOTES, 'UTF-8') ?>" class="text-decoration-none">
                                <?= htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8') ?>
                            </a>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php
    }
}
