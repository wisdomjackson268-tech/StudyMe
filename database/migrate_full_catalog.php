<?php
/**
 * StudyMe AI Platform — Standard Database Migration & Course Catalog Setup
 * Sets standard categories: Technology, Secondary / WAEC / NECO, University, Teacher
 * Populates all 20 Tech (₦10k), 12 Secondary (₦3k), 11 University (₦4k) courses with rich details.
 */
require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();
echo "Executing Standard Catalog Setup...\n";

// Disable foreign key checks for clean remapping
$pdo->exec("SET FOREIGN_KEY_CHECKS = 0;");

// 1. Ensure Categories table exists and has proper standard rows
$pdo->exec("
    CREATE TABLE IF NOT EXISTS categories (
        id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(150) NOT NULL UNIQUE,
        slug VARCHAR(180) NOT NULL UNIQUE,
        description TEXT,
        image VARCHAR(255),
        status ENUM('active','inactive') DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
");

// Remove legacy test categories or deactivate non-standard ones
$pdo->exec("UPDATE categories SET status = 'inactive' WHERE slug NOT IN ('technology', 'secondary-waec-neco', 'university', 'teacher')");

$standardCats = [
    ['Technology', 'technology', 'Master high-demand tech skills including coding, AI, cybersecurity, web and design.', 'bi-code-slash'],
    ['Secondary / WAEC / NECO', 'secondary-waec-neco', 'Comprehensive secondary school subjects with past question mastery for WAEC, NECO, and JAMB SSCE.', 'bi-book-half'],
    ['University', 'university', 'Undergraduate level courses in Computer Science, Sciences, Engineering, Economics, and Business Administration.', 'bi-mortarboard-fill'],
    ['Teacher', 'teacher', 'Educator development, pedagogy, curriculum planning, and interactive AI teaching tools.', 'bi-person-workspace']
];

foreach ($standardCats as $cat) {
    $stmt = $pdo->prepare("
        INSERT INTO categories (name, slug, description, image, status)
        VALUES (?, ?, ?, ?, 'active')
        ON DUPLICATE KEY UPDATE name = VALUES(name), description = VALUES(description), image = VALUES(image), status = 'active'
    ");
    $stmt->execute([$cat[0], $cat[1], $cat[2], $cat[3]]);
}

// Fetch map of category slugs to IDs
$catMap = [];
$res = $pdo->query("SELECT id, slug FROM categories WHERE status = 'active'")->fetchAll(PDO::FETCH_ASSOC);
foreach ($res as $r) {
    $catMap[$r['slug']] = (int)$r['id'];
}

echo "✔ Standard Categories Configured: " . implode(', ', array_keys($catMap)) . "\n";

// Ensure default teacher exists
$teacherStmt = $pdo->query("SELECT id FROM teachers LIMIT 1");
$teacherRow = $teacherStmt->fetch(PDO::FETCH_ASSOC);
if (!$teacherRow) {
    // Check if user 1 or teacher user exists
    $uRow = $pdo->query("SELECT id FROM users WHERE role = 'teacher' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $uId = $uRow ? (int)$uRow['id'] : 1;
    $pdo->prepare("INSERT INTO teachers (user_id, teacher_number, qualification, specialization, bio, rating, status) VALUES (?, 'TCH-001', 'Ph.D in Computer Science & Pedagogy', 'Lead Instructor', 'Experienced educator and mentor.', 4.95, 'active')")->execute([$uId]);
    $teacherId = $pdo->lastInsertId();
} else {
    $teacherId = (int)$teacherRow['id'];
}

// 2. SEED 20 TECHNOLOGY COURSES (₦10,000)
$techCourses = [
    [
        'title' => 'Web Development Bootcamp (Full Stack)',
        'slug' => 'web-development',
        'short' => 'Build modern responsive websites and web applications using HTML, CSS, JavaScript, PHP, and MySQL.',
        'desc' => "Learn full stack web development from foundational HTML5/CSS3 to advanced JavaScript, PHP backend programming, REST APIs, and database modeling.\n\nWhat You Will Learn:\n• Frontend responsive layout design with CSS Flexbox, Grid, and Bootstrap\n• Modern JavaScript DOM manipulation, Async/Await, and Fetch API\n• Server-side backend programming with clean PHP and MySQL PDO\n• Database modeling, relationships, prepared statements, and security\n• User authentication, role-based access control, and session management\n• Building production web applications from scratch\n\nSkills Gained:\n• Full-Stack Architecture\n• Database Design & SQL\n• API Integration & Security Best Practices\n• Git & Version Control\n\nCareer Opportunities:\n• Full Stack Web Developer\n• Frontend Developer\n• Backend Developer\n• Freelance Web Specialist",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 3600,
        'thumb' => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80'
    ],
    [
        'title' => 'Cybersecurity & Ethical Hacking Essentials',
        'slug' => 'cybersecurity',
        'short' => 'Understand network security, threat defense, ethical penetration testing, and digital asset protection.',
        'desc' => "Comprehensive introduction to cybersecurity fundamentals, threat modeling, vulnerability assessment, and securing digital infrastructure.\n\nWhat You Will Learn:\n• Fundamentals of network security, OSI model, and firewalls\n• Threat detection, malware analysis, and risk mitigation\n• Ethical hacking methodologies, penetration testing, and Kali Linux tools\n• Web application security vulnerabilities (OWASP Top 10)\n• Cryptography, SSL/TLS, and secure communication protocols\n\nSkills Gained:\n• Penetration Testing Basics\n• Vulnerability Scanning & Analysis\n• Security Policy Implementation\n• Incident Response Foundations\n\nCareer Opportunities:\n• Cybersecurity Analyst\n• SOC Tier 1 Analyst\n• Information Security Officer\n• Security Consultant",
        'level' => 'intermediate',
        'price' => 10000.00,
        'duration' => 2800,
        'thumb' => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80'
    ],
    [
        'title' => 'Mobile App Development (Flutter & React Native)',
        'slug' => 'app-development',
        'short' => 'Create cross-platform iOS and Android mobile apps with clean UI, state management, and cloud backends.',
        'desc' => "Build beautiful native-feeling mobile applications for iOS and Android with single codebase frameworks, reactive UI design, state management, and real-time APIs.\n\nWhat You Will Learn:\n• Dart & Flutter framework core concepts\n• Reactive UI widgets, navigation, and animations\n• State management with Provider and Riverpod\n• Connecting mobile apps to RESTful APIs and Firebase\n• Native device feature access (Camera, Geolocation, Storage)\n• Publishing to Google Play Store and Apple App Store\n\nSkills Gained:\n• Cross-Platform Mobile Engineering\n• Mobile UI/UX Implementation\n• Firebase Cloud Backend Integration\n• Mobile App Performance Optimization\n\nCareer Opportunities:\n• Mobile Application Developer\n• Flutter Developer\n• Cross-Platform Engineer\n• Freelance App Creator",
        'level' => 'intermediate',
        'price' => 10000.00,
        'duration' => 3200,
        'thumb' => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=600&q=80'
    ],
    [
        'title' => 'Data Analytics with Excel, Power BI & SQL',
        'slug' => 'data-analytics',
        'short' => 'Transform raw business data into actionable visual dashboards and strategic intelligence.',
        'desc' => "Learn the end-to-end data analytics workflow: data cleaning, SQL query extraction, exploratory data analysis, and interactive Power BI dashboards.\n\nWhat You Will Learn:\n• Advanced Excel formulas, Pivot Tables, and VLOOKUP/XLOOKUP\n• SQL queries: JOINs, Aggregations, Window Functions, and CTEs\n• Power BI data modeling, DAX expressions, and visual dashboards\n• Statistical data interpretation and business KPI reporting\n• Presenting data-driven insights to executive stakeholders\n\nSkills Gained:\n• SQL Query Optimization\n• Business Intelligence Reporting\n• DAX & Power BI Dashboarding\n• Data Storytelling & Presentation\n\nCareer Opportunities:\n• Data Analyst\n• Business Intelligence (BI) Analyst\n• Operations Data Specialist\n• Market Research Analyst",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2400,
        'thumb' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&q=80'
    ],
    [
        'title' => 'Computer Basics & Digital Literacy Masterclass',
        'slug' => 'computer-basics',
        'short' => 'Master computing essentials, operating systems, cloud productivity tools, and internet safety.',
        'desc' => "Ideal for absolute beginners to master computer hardware, operating system navigation, Microsoft Office Suite, Google Workspace, email etiquette, and safe internet browsing.\n\nWhat You Will Learn:\n• Computer hardware components, storage, memory, and peripherals\n• Windows/macOS file management, shortcuts, and troubleshooting\n• Word processing, spreadsheet calculations, and presentations\n• Cloud storage (Google Drive, OneDrive) and online collaboration\n• Cyber hygiene, safe browsing, password management, and anti-phishing\n\nSkills Gained:\n• Digital Workspace Productivity\n• Operating System Proficiency\n• Cloud Collaboration Tools\n• Fundamental Tech Problem Solving\n\nCareer Opportunities:\n• Administrative Assistant\n• Office Technology Specialist\n• Data Entry Officer\n• Customer Care Representative",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 1800,
        'thumb' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?w=600&q=80'
    ],
    [
        'title' => 'AI Content Creation & Prompt Engineering',
        'slug' => 'ai-content-creation',
        'short' => 'Leverage cutting-edge generative AI models to create professional copy, visuals, and multimedia assets.',
        'desc' => "Discover how to harness generative AI tools (ChatGPT, Midjourney, Claude, ElevenLabs) to produce high-impact marketing copy, graphic assets, video scripts, and automated workflows.\n\nWhat You Will Learn:\n• Advanced prompt engineering frameworks and system instructions\n• Creating engaging marketing copy, articles, and social media campaigns\n• Generating professional AI graphics, concept art, and product mockups\n• AI-assisted audio generation, voice synthesis, and video production\n• Ethical AI considerations, copyright awareness, and quality control\n\nSkills Gained:\n• Prompt Engineering Mastery\n• Generative AI Multimodal Workflow\n• AI-Powered Copywriting\n• Automated Content Production\n\nCareer Opportunities:\n• AI Content Creator\n• Prompt Engineer\n• Digital Growth Marketer\n• Creative Director",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2000,
        'thumb' => 'https://images.unsplash.com/photo-1677442136019-21780efad99a?w=600&q=80'
    ],
    [
        'title' => 'AutoCAD 2D & 3D Architectural Drafting',
        'slug' => 'autocad',
        'short' => 'Master precision technical drawing, floor plans, sections, and 3D architectural modeling.',
        'desc' => "Practical CAD training for architects, engineers, and designers to produce industry-standard 2D floor plans, elevations, structural details, and realistic 3D renderings.\n\nWhat You Will Learn:\n• AutoCAD interface, precision drafting tools, and coordinate systems\n• Creating architectural floor plans, elevations, and structural sections\n• Dimensioning standards, layers, blocks, and hatch patterns\n• Isometric drawing and 3D solid modeling\n• Plotting, sheet sets, and exporting technical blueprints\n\nSkills Gained:\n• Architectural Technical Drawing\n• 2D/3D Precision Drafting\n• Engineering Blueprint Generation\n• Industry CAD Standards\n\nCareer Opportunities:\n• CAD Drafter\n• Architectural Technician\n• Structural Engineering Assistant\n• Interior Design Modeler",
        'level' => 'intermediate',
        'price' => 10000.00,
        'duration' => 3000,
        'thumb' => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=600&q=80'
    ],
    [
        'title' => 'Graphic Design Masterclass (Photoshop & Illustrator)',
        'slug' => 'graphic-design',
        'short' => 'Create stunning branding, logos, social media graphics, and print assets with Adobe Photoshop and Illustrator.',
        'desc' => "Learn visual design principles, typography, color theory, image manipulation in Adobe Photoshop, and vector illustration in Adobe Illustrator.\n\nWhat You Will Learn:\n• Core graphic design theory: color harmony, typography, and layout balance\n• Advanced image editing, photo manipulation, and retouching in Photoshop\n• Vector illustration, logo creation, and icon sets in Illustrator\n• Designing brand identity packages, business cards, and marketing flyers\n• Exporting files for print and digital publishing\n\nSkills Gained:\n• Brand Identity Design\n• Vector Illustration\n• Photo Retouching & Composite Art\n• Visual Storytelling\n\nCareer Opportunities:\n• Graphic Designer\n• Brand Identity Specialist\n• Digital Illustrator\n• Visual Content Specialist",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2800,
        'thumb' => 'https://images.unsplash.com/photo-1626785774573-4b799315345d?w=600&q=80'
    ],
    [
        'title' => 'UI/UX Design with Figma (Zero to Pro)',
        'slug' => 'ui-ux-design',
        'short' => 'Design user-friendly mobile and web interfaces with Figma, wireframing, interactive prototyping, and UX research.',
        'desc' => "Master user experience and user interface design from scratch. Conduct user research, construct wireframes, design atomic design systems in Figma, and build clickable prototypes.\n\nWhat You Will Learn:\n• UX design thinking, user personas, journey mapping, and empathy maps\n• Wireframing and information architecture\n• High-fidelity UI design in Figma using Auto-Layout and Components\n• Creating design systems, color styles, typography scales, and token sets\n• Interactive prototyping, micro-interactions, and usability testing\n\nSkills Gained:\n• Figma Professional Mastery\n• Wireframing & Prototyping\n• Design System Architecture\n• User Experience Research\n\nCareer Opportunities:\n• UI/UX Designer\n• Product Designer\n• Mobile App UX Specialist\n• Design System Specialist",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 3000,
        'thumb' => 'https://images.unsplash.com/photo-1581291518655-9523c932dede?w=600&q=80'
    ],
    [
        'title' => 'Python Programming (Beginner to Advanced)',
        'slug' => 'python-programming',
        'short' => 'Master Python syntax, object-oriented programming, file processing, and automation scripts.',
        'desc' => "The complete Python coding curriculum covering core syntax, data structures (lists, tuples, dicts), functions, object-oriented programming, and real-world automation.\n\nWhat You Will Learn:\n• Python fundamentals: variables, control flow, loops, and data structures\n• Functional programming, list comprehensions, and error handling\n• Object-Oriented Programming (OOP): classes, inheritance, and polymorphism\n• Working with files (CSV, JSON), APIs, and third-party libraries\n• Web scraping with BeautifulSoup and automation scripting\n\nSkills Gained:\n• Python OOP Architecture\n• Scripting & Automation\n• Data Parsing & API Consumption\n• Algorithmic Problem Solving\n\nCareer Opportunities:\n• Python Developer\n• Automation Engineer\n• Backend Developer\n• Data Engineer Assistant",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 3400,
        'thumb' => 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=600&q=80'
    ],
    [
        'title' => 'Software Development Fundamentals & Algorithms',
        'slug' => 'software-development',
        'short' => 'Build strong foundations in data structures, algorithms, system design, and clean code practices.',
        'desc' => "Master computer science principles essential for technical interviews and scalable software engineering. Understand Big O complexity, sorting algorithms, trees, and system design.\n\nWhat You Will Learn:\n• Algorithmic complexity analysis (Big O notation: Time & Space)\n• Linear & non-linear data structures: Arrays, Linked Lists, Stacks, Queues, Trees, Graphs\n• Sorting, searching, recursion, and dynamic programming algorithms\n• Software architecture patterns, SOLID principles, and clean code conventions\n• Technical interview preparation and coding challenge workflows\n\nSkills Gained:\n• Algorithmic Thinking\n• Data Structures Mastery\n• System Architecture Basics\n• Clean Code & Refactoring\n\nCareer Opportunities:\n• Software Engineer\n• Junior Backend Engineer\n• Systems Programmer\n• Technical Consultant",
        'level' => 'intermediate',
        'price' => 10000.00,
        'duration' => 3600,
        'thumb' => 'https://images.unsplash.com/photo-1555066931-4365d14bab8c?w=600&q=80'
    ],
    [
        'title' => 'Digital Marketing, SEO & Growth Strategies',
        'slug' => 'digital-marketing',
        'short' => 'Drive online traffic, optimize conversion funnels, execute paid advertising, and master search engine optimization.',
        'desc' => "End-to-end digital marketing blueprint covering Search Engine Optimization (SEO), Google Ads, Meta Ads (Facebook/Instagram), email marketing funnels, and analytics tracking.\n\nWhat You Will Learn:\n• On-page, off-page, and technical Search Engine Optimization (SEO)\n• PPC advertising campaigns across Google Search, Display, and Meta Ads\n• Social media marketing strategy and community engagement\n• Email marketing automation, lead generation, and sales funnels\n• Conversion rate optimization (CRO) and Google Analytics 4 (GA4)\n\nSkills Gained:\n• SEO & Keyword Research\n• Paid Campaign Management\n• Funnel Optimization\n• Web Analytics & Attribution\n\nCareer Opportunities:\n• Digital Marketing Specialist\n• SEO Strategist\n• Performance Marketing Manager\n• Social Media Manager",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2400,
        'thumb' => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&q=80'
    ],
    [
        'title' => 'Computer Networking & CCNA Foundation',
        'slug' => 'computer-networking',
        'short' => 'Understand IPv4/IPv6 subnetting, routing protocols, switches, VLANs, and network architecture.',
        'desc' => "Solid foundation in computer networking aligned with Cisco CCNA fundamentals. Configure routers, switches, subnets, DHCP, DNS, and troubleshoot local and wide area networks.\n\nWhat You Will Learn:\n• OSI model and TCP/IP protocol suite deep-dive\n• IPv4 subnetting calculations and IPv6 addressing\n• Cisco IOS command line, router and switch configuration\n• VLANs, trunking, Spanning Tree Protocol (STP), and routing (OSPF, BGP)\n• Network troubleshooting, packet analysis with Wireshark, and NAT\n\nSkills Gained:\n• Network Design & Subnetting\n• Router & Switch Configuration\n• Protocol Troubleshooting\n• Network Security Basics\n\nCareer Opportunities:\n• Network Administrator\n• NOC Engineer\n• IT Support Specialist\n• Systems Administrator",
        'level' => 'intermediate',
        'price' => 10000.00,
        'duration' => 3000,
        'thumb' => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600&q=80'
    ],
    [
        'title' => 'Cloud Computing with AWS & Azure Fundamentals',
        'slug' => 'cloud-computing',
        'short' => 'Deploy scalable cloud infrastructure, virtual servers, storage buckets, and serverless applications.',
        'desc' => "Understand cloud architecture models (IaaS, PaaS, SaaS) and deploy real-world cloud workloads using Amazon Web Services (AWS) and Microsoft Azure.\n\nWhat You Will Learn:\n• Cloud computing architecture, global infrastructure, and high availability\n• AWS core services: EC2 instances, S3 storage, VPC networks, and RDS databases\n• Microsoft Azure essentials: Virtual Machines, Blob Storage, and Azure AD\n• Serverless computing with AWS Lambda and cloud security IAM policies\n• Cloud billing, monitoring with CloudWatch, and disaster recovery\n\nSkills Gained:\n• AWS Cloud Architecture\n• Virtual Server Deployment\n• Cloud Storage & Networking\n• Identity & Access Management (IAM)\n\nCareer Opportunities:\n• Cloud Support Associate\n• Junior DevOps Engineer\n• Cloud Solutions Specialist\n• Systems Infrastructure Analyst",
        'level' => 'intermediate',
        'price' => 10000.00,
        'duration' => 3200,
        'thumb' => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&q=80'
    ],
    [
        'title' => 'Database Management with MySQL & PostgreSQL',
        'slug' => 'database-management',
        'short' => 'Design relational database schemas, write complex queries, optimize indexes, and ensure data integrity.',
        'desc' => "Master relational database architecture, entity-relationship diagrams (ERD), normalization (1NF-3NF), complex SQL queries, transactions, and indexing performance.\n\nWhat You Will Learn:\n• Relational database theory and Entity Relationship Modeling (ERD)\n• Database normalization principles to eliminate data redundancy\n• Writing advanced SQL: Subqueries, Joins, Window Functions, Triggers, Views\n• Indexing strategies, query execution plans (EXPLAIN), and performance tuning\n• Database backup, restoration, user roles, and security permissions\n\nSkills Gained:\n• Relational Database Modeling\n• Advanced SQL Querying\n• Index & Performance Optimization\n• Database Administration\n\nCareer Opportunities:\n• Database Administrator (DBA)\n• SQL Developer\n• Backend Data Engineer\n• Application Database Specialist",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2800,
        'thumb' => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=600&q=80'
    ],
    [
        'title' => 'AI & Machine Learning with Python & Scikit-Learn',
        'slug' => 'ai-machine-learning',
        'short' => 'Build predictive models, classification algorithms, neural networks, and computer vision systems.',
        'desc' => "Practical introduction to applied Machine Learning using Python libraries (NumPy, Pandas, Matplotlib, Scikit-Learn, TensorFlow). Train regression, classification, and clustering models.\n\nWhat You Will Learn:\n• Data preprocessing, feature engineering, and exploratory data analysis\n• Supervised learning: Linear Regression, Logistic Regression, Decision Trees, Random Forests\n• Unsupervised learning: K-Means Clustering and Principal Component Analysis (PCA)\n• Model evaluation metrics: Confusion Matrix, ROC-AUC, Precision/Recall\n• Deep Learning introduction: Neural Networks and Computer Vision basics\n\nSkills Gained:\n• Machine Learning Algorithms\n• Data Preprocessing & Feature Engineering\n• Model Evaluation & Hyperparameter Tuning\n• Predictive Analytics Modeling\n\nCareer Opportunities:\n• Junior Machine Learning Engineer\n• AI Research Assistant\n• Data Scientist\n• Applied Intelligence Specialist",
        'level' => 'advanced',
        'price' => 10000.00,
        'duration' => 4000,
        'thumb' => 'https://images.unsplash.com/photo-1507146426996-ef05306b995a?w=600&q=80'
    ],
    [
        'title' => 'Professional Video Editing (Premiere Pro & CapCut)',
        'slug' => 'video-editing',
        'short' => 'Produce cinematic videos, engaging social media reels, color grading, and dynamic audio effects.',
        'desc' => "Learn professional non-linear video editing workflows in Adobe Premiere Pro and CapCut Desktop. Master cuts, transitions, sound design, color grading, and exporting high-res media.\n\nWhat You Will Learn:\n• Timeline workflow, keyframe animations, and multi-camera editing\n• Dynamic text titles, animated lower thirds, and motion graphics\n• Audio mixing, background music ducking, and voiceover enhancement\n• Color correction, LUT grading, and cinematic color palettes\n• Rendering optimized vertical video formats for TikTok, YouTube Shorts, and Reels\n\nSkills Gained:\n• Non-Linear Video Editing\n• Color Grading & Correction\n• Audio Design & Mixing\n• Motion Graphics & Lower Thirds\n\nCareer Opportunities:\n• Professional Video Editor\n• Content Creator / Reel Producer\n• YouTube Video Strategist\n• Media Post-Production Specialist",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2600,
        'thumb' => 'https://images.unsplash.com/photo-1574717024653-61fd2cf4d44d?w=600&q=80'
    ],
    [
        'title' => '3D Design & Animation with Blender',
        'slug' => '3d-design-animation',
        'short' => 'Model 3D assets, apply realistic materials and lighting, rig characters, and create 3D animations.',
        'desc' => "Comprehensive 3D modeling, texturing, lighting, rigging, and animation course in Blender for video games, visual effects, architectural visualization, and digital art.\n\nWhat You Will Learn:\n• Blender interface, polygonal mesh modeling, and sculpting tools\n• UV unwrapping, PBR material creation, and procedural shader nodes\n• Studio lighting setups with Eevee and Cycles render engines\n• Character rigging, bone constraints, and keyframe animation\n• Rendering production-quality images and video sequences\n\nSkills Gained:\n• 3D Polygonal Mesh Modeling\n• PBR Texturing & Shading\n• Lighting & Camera Direction\n• Keyframe Animation & Rigging\n\nCareer Opportunities:\n• 3D Generalist\n• Game Asset Modeler\n• 3D Animator\n• Motion Graphics Artist",
        'level' => 'intermediate',
        'price' => 10000.00,
        'duration' => 3600,
        'thumb' => 'https://images.unsplash.com/photo-1618005182384-a83a8bd57fbe?w=600&q=80'
    ],
    [
        'title' => 'Technical Writing & API Documentation',
        'slug' => 'technical-writing',
        'short' => 'Write clear developer documentation, REST API guides, user manuals, and technical release notes.',
        'desc' => "Learn to communicate complex technical concepts with simplicity and precision. Create Markdown docs, OpenAPI/Swagger specifications, developer portals, and user documentation.\n\nWhat You Will Learn:\n• Principles of clear, concise, and structured technical documentation\n• Documenting RESTful APIs, endpoints, authentication, and error responses\n• Docs-as-Code workflow using Markdown, Git, and static site generators\n• Creating user guides, troubleshooting FAQs, and system architecture briefs\n• OpenAPI (Swagger) specification creation and interactive documentation\n\nSkills Gained:\n• API Reference Documentation\n• Docs-as-Code Workflow\n• Technical Communication\n• Markdown & Swagger Tooling\n\nCareer Opportunities:\n• Technical Writer\n• Developer Documentation Specialist\n• Technical Content Manager\n• Software Documentation Lead",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2200,
        'thumb' => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?w=600&q=80'
    ],
    [
        'title' => 'IT Support & Hardware Troubleshooting',
        'slug' => 'it-support-troubleshooting',
        'short' => 'Diagnose hardware failures, configure enterprise operating systems, and manage helpdesk tickets.',
        'desc' => "Prepare for entry-level IT careers (CompTIA A+ aligned). Learn PC assembly, OS installation, printer setup, system diagnostics, malware removal, and ticketing systems.\n\nWhat You Will Learn:\n• PC component diagnosis: Motherboard, CPU, RAM, PSU, and SSDs\n• Operating system installation, imaging, recovery, and registry repairs\n• Managing user accounts, permissions, and Active Directory foundations\n• Peripheral setup: Network printers, scanners, and display docks\n• Helpdesk ticketing workflow, customer communication, and remote desktop support\n\nSkills Gained:\n• Computer Hardware Troubleshooting\n• Operating System Recovery\n• IT Helpdesk Ticketing\n• Remote Diagnostic Support\n\nCareer Opportunities:\n• IT Support Specialist\n• Desktop Support Technician\n• Help Desk Analyst\n• Hardware Support Specialist",
        'level' => 'beginner',
        'price' => 10000.00,
        'duration' => 2400,
        'thumb' => 'https://images.unsplash.com/photo-1588508065123-287b28e013da?w=600&q=80'
    ]
];

$techCatId = $catMap['technology'];
foreach ($techCourses as $tc) {
    $stmt = $pdo->prepare("
        INSERT INTO courses (teacher_id, category_id, title, slug, short_description, description, level, price, duration_minutes, thumbnail, status, featured, certificate_enabled, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'published', 1, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            category_id = VALUES(category_id),
            title = VALUES(title),
            short_description = VALUES(short_description),
            description = VALUES(description),
            level = VALUES(level),
            price = VALUES(price),
            duration_minutes = VALUES(duration_minutes),
            thumbnail = VALUES(thumbnail),
            status = 'published'
    ");
    $stmt->execute([$teacherId, $techCatId, $tc['title'], $tc['slug'], $tc['short'], $tc['desc'], $tc['level'], $tc['price'], $tc['duration'], $tc['thumb']]);
}
echo "✔ 20 Technology Courses Configured (₦10,000 each)\n";

// 3. SEED 12 SECONDARY / WAEC / NECO COURSES (₦3,000)
$secondaryCourses = [
    [
        'title' => 'Mathematics (WAEC/NECO/JAMB Prep)',
        'slug' => 'secondary-mathematics',
        'short' => 'Complete high school syllabus: Algebra, Geometry, Trigonometry, Statistics, and Calculus with step-by-step WAEC past questions.',
        'desc' => "Comprehensive preparation for WAEC, NECO, and JAMB Mathematics. Covers Quadratic Equations, Surds, Matrices, Coordinate Geometry, Trigonometry, and Probability.\n\nWhat You Will Learn:\n• Surds, Logarithms, Number Bases, and Modular Arithmetic\n• Quadratic equations, Simultaneous equations, and Polynomials\n• Plane Geometry, Circle Theorems, and Mensuration\n• Trigonometric Ratios, Sine & Cosine rules, and Bearing & Distances\n• Statistics (Mean, Median, Standard Deviation) and Probability\n• Step-by-step solutions to 10+ years of past WAEC & NECO examination questions\n\nSkills Gained:\n• Mathematical Problem Solving\n• Step-by-Step Proofs & Calculations\n• Examination Time Management\n• Conceptual Clarity",
        'level' => 'all_levels',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=600&q=80'
    ],
    [
        'title' => 'English Language & Oral English (WAEC/NECO)',
        'slug' => 'secondary-english',
        'short' => 'Master English grammar, essay writing, summary techniques, comprehension passages, and test of orals.',
        'desc' => "Ace your WAEC and NECO English Language papers. Master formal/informal essays, summary writing methods, comprehension techniques, idioms, and vowel/consonant phonetics.\n\nWhat You Will Learn:\n• Parts of speech, clauses, tenses, and sentence construction rules\n• Essay writing strategies: Narrative, Descriptive, Expository, and Argumentative\n• Summary writing techniques: Extracting main points accurately\n• Reading comprehension analysis and contextual vocabulary\n• Test of Orals: Vowel sounds, consonants, stress patterns, and intonation\n\nSkills Gained:\n• Professional English Grammar\n• Essay & Letter Writing\n• Precision Summary Skills\n• Oral Phonetics & Pronunciation",
        'level' => 'all_levels',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1456513080510-7bf3a84b82f8?w=600&q=80'
    ],
    [
        'title' => 'Physics (SSCE / WAEC / NECO)',
        'slug' => 'secondary-physics',
        'short' => 'Master Mechanics, Waves, Sound, Optics, Electricity, Magnetism, and Atomic Physics with practical theory.',
        'desc' => "Understand high school physics concepts with clear explanations and numerical problem-solving for WAEC and NECO.\n\nWhat You Will Learn:\n• Scalars, Vectors, Kinematics, Dynamics, and Projectile Motion\n• Work, Energy, Power, Machines, and Circular Motion\n• Wave motion, Sound properties, Resonance, and Doppler effect\n• Geometric Optics: Reflection, Refraction, Lenses, and Optical Instruments\n• Current Electricity, Ohm's Law, Electric Fields, and Magnetism\n• Nuclear Physics, Radioactivity, and Half-Life calculations",
        'level' => 'intermediate',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?w=600&q=80'
    ],
    [
        'title' => 'Chemistry (SSCE / WAEC / NECO)',
        'slug' => 'secondary-chemistry',
        'short' => 'Periodic table trends, chemical equations, stoichiometry, organic chemistry, and laboratory practicals.',
        'desc' => "Comprehensive SSCE Chemistry curriculum covering Atomic Structure, Chemical Bonding, Stoichiometric calculations, Electrochemistry, and Hydrocarbons.\n\nWhat You Will Learn:\n• Atomic structure, electronic configurations, and Periodic Table trends\n• Chemical bonding, Stoichiometry, and Mole concept calculations\n• Acids, Bases, Salts, pH calculations, and Volumetric analysis (Titration)\n• Chemical Equilibrium, Le Chatelier's Principle, and Rate of Reactions\n• Organic Chemistry: Alkanes, Alkenes, Alkynes, Alkanols, and Esters\n• Qualitative analysis of cations and anions for Chemistry Practical",
        'level' => 'intermediate',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80'
    ],
    [
        'title' => 'Biology (SSCE / WAEC / NECO)',
        'slug' => 'secondary-biology',
        'short' => 'Cell biology, plant & animal physiology, genetics, ecology, and biological diagram illustrations.',
        'desc' => "Master secondary school biology concepts: cell structure, nutrition, circulatory systems, respiration, genetics, evolution, and ecology.\n\nWhat You Will Learn:\n• Cell structure, organelles, mitosis, and meiosis cell division\n• Plant physiology: Photosynthesis, Transpiration, and Mineral nutrition\n• Animal physiology: Digestion, Circulation, Respiration, and Excretion\n• Genetics: Mendelian inheritance, monohybrid/dihybrid crosses, and DNA\n• Ecology: Food chains, energy flow, biomes, and conservation\n• Drawing and labeling biological specimens for practical exams",
        'level' => 'beginner',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1530210124550-912dc1381cb8?w=600&q=80'
    ],
    [
        'title' => 'Economics (WAEC / NECO / JAMB)',
        'slug' => 'secondary-economics',
        'short' => 'Demand and supply analysis, market structures, national income, banking systems, and international trade.',
        'desc' => "Master micro and macroeconomics principles for WAEC/NECO. Learn price elasticity, production theory, monetary policy, and public finance.\n\nWhat You Will Learn:\n• Basic economic concepts: Scarcity, Choice, Scale of Preference, Opportunity Cost\n• Demand and Supply laws, Equilibrium price, and Elasticity calculations\n• Production theory, Cost concepts, Revenue, and Market structures\n• National Income determination (GDP, GNP, NNP) and Inflation\n• Central Banking, Commercial Banking, and Monetary Policy tools\n• International Trade, Balance of Payments, and Globalization",
        'level' => 'beginner',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80'
    ],
    [
        'title' => 'Government & Civic Education (WAEC/NECO)',
        'slug' => 'secondary-government',
        'short' => 'Political concepts, constitutions, organs of government, Nigerian constitutional development, and foreign policy.',
        'desc' => "Comprehensive guide to Government and Civic Education. Explore sovereignty, separation of powers, colonial administration, constitutional milestones, and civic responsibilities.\n\nWhat You Will Learn:\n• Concepts of State, Nation, Sovereignty, and Rule of Law\n• Structures & Organs of Government: Legislature, Executive, Judiciary\n• Political systems: Democracy, Federalism, Unitary, and Parliamentary systems\n• Nigerian Constitutional Development (1922 Clifford to 1999 Constitution)\n• International Organizations: AU, ECOWAS, Commonwealth, and United Nations\n• Human rights, citizenship duties, and electoral processes",
        'level' => 'beginner',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&q=80'
    ],
    [
        'title' => 'Literature in English (WAEC/NECO)',
        'slug' => 'secondary-literature',
        'short' => 'Analysis of prescribed prose, drama, poetry, literary devices, characterization, and exam essay formatting.',
        'desc' => "In-depth textual analysis of approved African and non-African prose, drama, and poetry texts for WAEC/NECO examinations.\n\nWhat You Will Learn:\n• Literary devices: Metaphor, Simile, Irony, Allegory, Personification, Imagery\n• Character analysis, major themes, and contextual quotations in drama\n• Prose breakdown: Plot structure, setting, narrator point-of-view, and conflict\n• Poetic stanza analysis, rhyme schemes, meter, and tone\n• Structuring high-scoring literary critique essays in exams",
        'level' => 'all_levels',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1474932430478-367dbb6832c1?w=600&q=80'
    ],
    [
        'title' => 'Geography (WAEC / NECO)',
        'slug' => 'secondary-geography',
        'short' => 'Physical geography, map reading, contour interpretation, climate systems, and regional geography of Nigeria.',
        'desc' => "Master physical, human, and regional geography for WAEC and NECO with map work calculations and climate models.\n\nWhat You Will Learn:\n• Earth structure, plate tectonics, rocks, weathering, and landforms\n• Map reading: Scales, gradient, intervisibility, contours, and cross-sections\n• Weather and Climate: Atmosphere, precipitation, winds, and Köppen classification\n• Population geography, settlement patterns, urbanization, and migration\n• Regional geography of Nigeria: Mineral resources, agriculture, and industries",
        'level' => 'intermediate',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1524661135-423995f22d0b?w=600&q=80'
    ],
    [
        'title' => 'Computer Studies (SSCE / WAEC / NECO)',
        'slug' => 'secondary-computer-studies',
        'short' => 'Computer system architecture, logic gates, basic programming (BASIC/Python), networking, and word processing.',
        'desc' => "Aligned with the national SSCE curriculum: hardware architecture, binary arithmetic, logic circuits, basic coding, database concepts, and internet applications.\n\nWhat You Will Learn:\n• History of computing, generations of computers, and classifications\n• Logic gates (AND, OR, NOT, NAND, NOR) and truth tables\n• Binary, octal, and hexadecimal number system conversions\n• Fundamentals of computer programming: algorithms, flowcharts, variables\n• Computer maintenance, malware protection, and network topologies",
        'level' => 'beginner',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&q=80'
    ],
    [
        'title' => 'Further Mathematics (WAEC / NECO)',
        'slug' => 'secondary-further-maths',
        'short' => 'Advanced algebra, coordinate geometry, vectors, matrices, calculus, and mechanics for science students.',
        'desc' => "Advanced high school mathematics for aspiring STEM students preparing for SSCE and university entrance.\n\nWhat You Will Learn:\n• Indices, Logarithms, Surds, and Partial Fractions\n• Binomial expansion, Mathematical induction, and Sequence & Series\n• Calculus: Differential calculus, integration techniques, and applications\n• Vector algebra in 2D & 3D, dot product, and cross product\n• Mechanics: Resolution of forces, friction, projectile motion, and impulse",
        'level' => 'advanced',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&q=80'
    ],
    [
        'title' => 'Civic Education & Social Studies',
        'slug' => 'secondary-civic-education',
        'short' => 'National values, democratic institutions, human rights, drug abuse prevention, and responsible citizenship.',
        'desc' => "Essential curriculum for secondary school students covering national values, rule of law, anti-corruption, youth empowerment, and civic responsibilities.\n\nWhat You Will Learn:\n• Meaning of Civic Education, values, honesty, and integrity\n• Citizenship, rights, obligations, and constitutional provisions\n• Democracy, rule of law, and importance of free and fair elections\n• Dangers of drug abuse, cultism, trafficking, and prevention strategies\n• National consciousness, national symbols, and peaceful co-existence",
        'level' => 'beginner',
        'price' => 3000.00,
        'thumb' => 'https://images.unsplash.com/photo-1491841573634-28140fc7ced7?w=600&q=80'
    ]
];

$secCatId = $catMap['secondary-waec-neco'];
foreach ($secondaryCourses as $sc) {
    $stmt = $pdo->prepare("
        INSERT INTO courses (teacher_id, category_id, title, slug, short_description, description, level, price, duration_minutes, thumbnail, status, featured, certificate_enabled, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 1800, ?, 'published', 1, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            category_id = VALUES(category_id),
            title = VALUES(title),
            short_description = VALUES(short_description),
            description = VALUES(description),
            level = VALUES(level),
            price = VALUES(price),
            duration_minutes = VALUES(duration_minutes),
            thumbnail = VALUES(thumbnail),
            status = 'published'
    ");
    $stmt->execute([$teacherId, $secCatId, $sc['title'], $sc['slug'], $sc['short'], $sc['desc'], $sc['level'], $sc['price'], $sc['thumb']]);
}
echo "✔ 12 Secondary / WAEC / NECO Courses Configured (₦3,000 each)\n";

// 4. SEED 11 UNIVERSITY COURSES (₦4,000)
$uniCourses = [
    [
        'title' => 'Computer Science (Undergraduate Core)',
        'slug' => 'university-computer-science',
        'short' => 'Foundational university module covering discrete mathematics, data structures, computer architecture, and OS concepts.',
        'desc' => "Undergraduate university level computer science curriculum: Discrete structures, algorithm analysis, computer organization, operating systems, and database systems.\n\nWhat You Will Learn:\n• Discrete mathematics: Propositional logic, set theory, graph theory\n• CPU architecture, instruction set architectures (ISA), and memory hierarchy\n• Operating systems concepts: Processes, threads, concurrency, memory paging\n• Relational database theory and formal query languages (Relational Algebra)\n• Compilers, lexical analysis, and parsing fundamentals",
        'level' => 'intermediate',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&q=80'
    ],
    [
        'title' => 'University Calculus & Linear Algebra',
        'slug' => 'university-mathematics',
        'short' => 'Multivariable calculus, limits, differential equations, vector spaces, matrix eigenvalues, and eigenvectors.',
        'desc' => "Undergraduate university mathematics covering multivariable differential and integral calculus, vector spaces, linear transformations, and differential equations.\n\nWhat You Will Learn:\n• Limits, continuity, epsilon-delta proofs, and derivatives\n• Integration techniques: Integration by parts, trigonometric substitution\n• Multivariable calculus: Partial derivatives, gradient vectors, multiple integrals\n• Linear Algebra: Vector spaces, linear independence, basis, dimension\n• Matrix operations, determinants, eigenvalues, eigenvectors, and diagonalization",
        'level' => 'advanced',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&q=80'
    ],
    [
        'title' => 'University Physics & Classical Mechanics',
        'slug' => 'university-physics',
        'short' => 'Newtonian mechanics, rotational dynamics, gravitation, thermodynamics, and electromagnetic field equations.',
        'desc' => "Calculus-based university physics covering classical mechanics, rotational motion, thermodynamics, electromagnetism, and Maxwell equations.\n\nWhat You Will Learn:\n• Calculus-based Newtonian mechanics, energy conservation, momentum\n• Rotational kinematics, moment of inertia, and angular momentum\n• Fluid mechanics: Bernoulli equation, viscosity, and turbulence\n• Thermodynamics: First and second laws, Carnot cycles, and entropy\n• Electromagnetism: Gauss's Law, Ampere's Law, Faraday's Law, and Maxwell equations",
        'level' => 'advanced',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?w=600&q=80'
    ],
    [
        'title' => 'University General & Organic Chemistry',
        'slug' => 'university-chemistry',
        'short' => 'Reaction mechanisms, stereochemistry, thermodynamics, chemical kinetics, and spectroscopic analysis.',
        'desc' => "University level chemistry: Thermodynamic enthalpy and Gibbs free energy, reaction kinetics, organic reaction mechanisms (SN1, SN2, E1, E2), and NMR spectroscopy.\n\nWhat You Will Learn:\n• Chemical thermodynamics: Enthalpy, entropy, Gibbs free energy, spontaneity\n• Chemical kinetics: Rate laws, activation energy, and reaction mechanisms\n• Organic chemistry: IUPAC nomenclature, hybridization, and resonance\n• Reaction mechanisms: Nucleophilic substitution, elimination, and addition\n• Spectroscopy: Infrared (IR), UV-Vis, and Nuclear Magnetic Resonance (NMR)",
        'level' => 'advanced',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80'
    ],
    [
        'title' => 'General Biology & Molecular Genetics',
        'slug' => 'university-biology',
        'short' => 'Cellular biology, molecular genetics, DNA replication, gene expression, recombinant DNA, and ecology.',
        'desc' => "Undergraduate biological sciences: Cellular biochemistry, molecular genetics, recombinant DNA technology, evolutionary biology, and ecological systems.\n\nWhat You Will Learn:\n• Biomolecules: Structure and function of proteins, nucleic acids, lipids\n• Molecular genetics: DNA replication, transcription, RNA processing, translation\n• Gene regulation: Operons, transcription factors, epigenetics\n• Biotechnology: PCR, gel electrophoresis, CRISPR gene editing basics\n• Evolutionary biology, population genetics, and ecosystem dynamics",
        'level' => 'intermediate',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1530210124550-912dc1381cb8?w=600&q=80'
    ],
    [
        'title' => 'Principles of Economics & Macroeconomics',
        'slug' => 'university-economics',
        'short' => 'Consumer theory, market equilibrium, macroeconomic modeling (IS-LM), fiscal policy, and monetary economics.',
        'desc' => "Undergraduate university economics: Indifference curve analysis, production functions, game theory, macroeconomic equilibrium (IS-LM framework), and international monetary systems.\n\nWhat You Will Learn:\n• Consumer choice theory, utility maximization, and indifference curves\n• Firm behavior: Cost minimization, profit maximization, monopoly pricing\n• Macroeconomic modeling: Aggregate Demand/Supply, IS-LM framework\n• Monetary and fiscal policies, central bank interest rate mechanisms\n• International economics: Exchange rate regimes, trade policies, balance of payments",
        'level' => 'intermediate',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80'
    ],
    [
        'title' => 'Financial Accounting & Reporting Standards',
        'slug' => 'university-accounting',
        'short' => 'Financial statements preparation, double-entry bookkeeping, asset valuation, IFRS standards, and cash flow analysis.',
        'desc' => "University accounting curriculum: Principles of financial accounting, balance sheets, income statements, cash flow statements, and International Financial Reporting Standards (IFRS).\n\nWhat You Will Learn:\n• Double-entry accounting system, general ledger, and trial balance\n• Preparation of Statement of Financial Position, Profit & Loss, and Cash Flows\n• Inventory valuation (FIFO, Weighted Average) and depreciation methods\n• Accounting for liabilities, equity, shares, and bonds\n• Financial ratio analysis: Liquidity, profitability, efficiency, and solvency",
        'level' => 'beginner',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=600&q=80'
    ],
    [
        'title' => 'Business Administration & Management Strategy',
        'slug' => 'university-business-admin',
        'short' => 'Organizational behavior, strategic management, corporate governance, human resource management, and leadership.',
        'desc' => "Undergraduate business administration: Management theories, organizational leadership, strategic analysis (SWOT, Porter's Five Forces), marketing management, and operations.\n\nWhat You Will Learn:\n• Management principles: Planning, organizing, leading, and controlling (POLC)\n• Strategic management frameworks: SWOT analysis, PESTEL, Porter's Five Forces\n• Organizational behavior, workplace motivation, and corporate culture\n• Human Resource Management: Talent acquisition, performance appraisal\n• Operations management, supply chain principles, and project management",
        'level' => 'intermediate',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=600&q=80'
    ],
    [
        'title' => 'Probability Theory & Applied Statistics',
        'slug' => 'university-statistics',
        'short' => 'Probability distributions, hypothesis testing, ANOVA, linear regression, and statistical inference.',
        'desc' => "Undergraduate statistics: Probability axioms, discrete and continuous random variables, Central Limit Theorem, confidence intervals, hypothesis testing, and regression analysis.\n\nWhat You Will Learn:\n• Probability axioms, conditional probability, Bayes' Theorem\n• Discrete & continuous distributions: Binomial, Poisson, Normal, Exponential\n• Sampling distributions, Central Limit Theorem, and Point Estimation\n• Hypothesis testing: Z-tests, t-tests, Chi-square tests, and ANOVA\n• Simple and multiple linear regression, correlation coefficients",
        'level' => 'intermediate',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&q=80'
    ],
    [
        'title' => 'Information Technology & System Architecture',
        'slug' => 'university-information-tech',
        'short' => 'Enterprise systems, software architecture, cloud platforms, information security governance, and IT service management.',
        'desc' => "University IT curriculum: Enterprise architecture, systems analysis and design (UML), database management, cloud computing models, and cybersecurity governance.\n\nWhat You Will Learn:\n• Systems Development Life Cycle (SDLC), Agile and Waterfall methodologies\n• UML modeling: Use Case, Class, Sequence, and Activity diagrams\n• Enterprise IT infrastructure, cloud integration, and client-server architectures\n• Information security policies, risk assessment, and compliance frameworks\n• IT service management (ITIL) principles and project management",
        'level' => 'intermediate',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1519389950473-47ba0277781c?w=600&q=80'
    ],
    [
        'title' => 'Engineering Mechanics & Structural Analysis',
        'slug' => 'university-engineering-mechanics',
        'short' => 'Statics, dynamics, vector force equilibrium, truss analysis, stress-strain relations, and beam bending moments.',
        'desc' => "Undergraduate engineering foundation: Vector mechanics, static equilibrium, shear and moment diagrams, friction, kinematics, and materials stress analysis.\n\nWhat You Will Learn:\n• Force vectors, moments, couples, and free-body diagram equilibrium\n• Structural analysis: Trusses (Method of Joints & Sections), frames, and machines\n• Internal forces: Shear force and bending moment diagrams for beams\n• Stress and strain relations, Hooke's law, elasticity, and Mohr's circle\n• Particle and rigid-body planar dynamics: Newton's second law and energy methods",
        'level' => 'advanced',
        'price' => 4000.00,
        'thumb' => 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=600&q=80'
    ]
];

$uniCatId = $catMap['university'];
foreach ($uniCourses as $uc) {
    $stmt = $pdo->prepare("
        INSERT INTO courses (teacher_id, category_id, title, slug, short_description, description, level, price, duration_minutes, thumbnail, status, featured, certificate_enabled, created_at, updated_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, 2400, ?, 'published', 1, 1, NOW(), NOW())
        ON DUPLICATE KEY UPDATE 
            category_id = VALUES(category_id),
            title = VALUES(title),
            short_description = VALUES(short_description),
            description = VALUES(description),
            level = VALUES(level),
            price = VALUES(price),
            duration_minutes = VALUES(duration_minutes),
            thumbnail = VALUES(thumbnail),
            status = 'published'
    ");
    $stmt->execute([$teacherId, $uniCatId, $uc['title'], $uc['slug'], $uc['short'], $uc['desc'], $uc['level'], $uc['price'], $uc['thumb']]);
}
echo "✔ 11 University Courses Configured (₦4,000 each)\n";

// Enable foreign key checks
$pdo->exec("SET FOREIGN_KEY_CHECKS = 1;");

echo "All catalog setup completed successfully!\n";
