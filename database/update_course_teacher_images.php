<?php

require_once dirname(__DIR__) . '/config/main.php';

$pdo = getDBConnection();

$courseImageMap = [

    'web-development'                   => 'https://images.unsplash.com/photo-1498050108023-c5249f4df085?w=600&q=80',
    'cybersecurity'                     => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80',
    'app-development'                   => 'https://images.unsplash.com/photo-1512941937669-90a1b58e7e9c?w=600&q=80',
    'data-analytics'                    => 'https://images.unsplash.com/photo-1551288049-bebda4e38f71?w=600&q=80',
    'python-programming'                => 'https://images.unsplash.com/photo-1526379095098-d400fd0bf935?w=600&q=80',
    'machine-learning-ai'               => 'https://images.unsplash.com/photo-1677442136019-21780ecad995?w=600&q=80',
    'cloud-devops'                      => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&q=80',
    'ui-ux-design'                      => 'https://images.unsplash.com/photo-1581291518655-9523c932edcf?w=600&q=80',
    'blockchain-development'            => 'https://images.unsplash.com/photo-1639762681485-074b7f938ba0?w=600&q=80',
    'game-development'                  => 'https://images.unsplash.com/photo-1550745165-9bc0b252726f?w=600&q=80',
    'product-management'                => 'https://images.unsplash.com/photo-1531403009284-440f080d1e12?w=600&q=80',
    'digital-marketing'                 => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&q=80',
    'linux-admin'                       => 'https://images.unsplash.com/photo-1629654297299-c8506221ca97?w=600&q=80',
    'database-design'                   => 'https://images.unsplash.com/photo-1544383835-bda2bc66a55d?w=600&q=80',
    'quality-assurance'                 => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&q=80',
    'iot-embedded-systems'              => 'https://images.unsplash.com/photo-1518770660439-4636190af475?w=600&q=80',
    'networking-ccna'                   => 'https://images.unsplash.com/photo-1544197150-b99a580bb7a8?w=600&q=80',
    'technical-writing'                 => 'https://images.unsplash.com/photo-1455390582262-044cdead277a?w=600&q=80',
    'ar-vr-development'                 => 'https://images.unsplash.com/photo-1592478411213-6153e4ebc07d?w=600&q=80',
    'tech-sales'                        => 'https://images.unsplash.com/photo-1552664730-d307ca884978?w=600&q=80',

    'mathematics-ssce'                  => 'https://images.unsplash.com/photo-1635070041078-e363dbe005cb?w=600&q=80',
    'english-language-ssce'             => 'https://images.unsplash.com/photo-1457369804613-52c61a468e7d?w=600&q=80',
    'physics-ssce'                      => 'https://images.unsplash.com/photo-1636466497217-26a8cbeaf0aa?w=600&q=80',
    'chemistry-ssce'                    => 'https://images.unsplash.com/photo-1532187863486-abf9dbad1b69?w=600&q=80',
    'biology-ssce'                      => 'https://images.unsplash.com/photo-1530026405186-ed1f139313f8?w=600&q=80',
    'further-mathematics-ssce'          => 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&q=80',
    'economics-ssce'                    => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80',
    'financial-accounting-ssce'         => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=600&q=80',
    'commerce-ssce'                     => 'https://images.unsplash.com/photo-1586528116311-ad8dd3c8310d?w=600&q=80',
    'government-ssce'                   => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&q=80',
    'literature-in-english-ssce'        => 'https://images.unsplash.com/photo-1474932430478-367dbb6832c1?w=600&q=80',
    'geography-ssce'                    => 'https://images.unsplash.com/photo-1524661135-423995f22d0b?w=600&q=80',
    'agricultural-science-ssce'         => 'https://images.unsplash.com/photo-1500937386664-56d1dfef3854?w=600&q=80',
    'civic-education-ssce'              => 'https://images.unsplash.com/photo-1540910419892-4a36d2c3266c?w=600&q=80',
    'computer-studies-ssce'             => 'https://images.unsplash.com/photo-1516321318423-f06f85e504b3?w=600&q=80',
    'technical-drawing-ssce'            => 'https://images.unsplash.com/photo-1503387762-592deb58ef4e?w=600&q=80',
    'crk-ssce'                          => 'https://images.unsplash.com/photo-1490730141103-6cac27aaab94?w=600&q=80',
    'irk-ssce'                          => 'https://images.unsplash.com/photo-1584551246679-0daf3d275d0f?w=600&q=80',
    'animal-husbandry-ssce'             => 'https://images.unsplash.com/photo-1527153857715-3908f2ae5e81?w=600&q=80',

    'civil-engineering'                 => 'https://images.unsplash.com/photo-1581092160607-ee22621dd758?w=600&q=80',
    'mechanical-engineering'            => 'https://images.unsplash.com/photo-1537462715879-360eeb61a0ad?w=600&q=80',
    'electrical-engineering'            => 'https://images.unsplash.com/photo-1473341304170-971dccb5ac1e?w=600&q=80',
    'electronics-engineering'           => 'https://images.unsplash.com/photo-1517077304055-6e89abbf09b0?w=600&q=80',
    'electrical-electronics-engineering'=> 'https://images.unsplash.com/photo-1509228468518-180dd4864904?w=600&q=80',
    'computer-engineering'              => 'https://images.unsplash.com/photo-1550751827-4bd374c3f58b?w=600&q=80',
    'chemical-engineering'              => 'https://images.unsplash.com/photo-1532094349884-543bc11b234d?w=600&q=80',
    'petroleum-engineering'             => 'https://images.unsplash.com/photo-1518709268805-4e9042af9f23?w=600&q=80',
    'agricultural-engineering'          => 'https://images.unsplash.com/photo-1586771107445-d3ca888129ff?w=600&q=80',
    'mechatronics-engineering'          => 'https://images.unsplash.com/photo-1485827404703-89b55fcc595e?w=600&q=80',
    'aerospace-engineering'             => 'https://images.unsplash.com/photo-1517976487502-5f653457a346?w=600&q=80',
    'industrial-production-engineering' => 'https://images.unsplash.com/photo-1581091226825-a6a2a5aee158?w=600&q=80',
    'metallurgical-materials-engineering'=> 'https://images.unsplash.com/photo-1504917599217-d4dc5ebe6122?w=600&q=80',
    'biomedical-engineering'            => 'https://images.unsplash.com/photo-1576091160399-112ba8d25d1d?w=600&q=80',
    'food-science-technology'           => 'https://images.unsplash.com/photo-1505751172876-fa1923c5c528?w=600&q=80',
    'water-resources-engineering'       => 'https://images.unsplash.com/photo-1468421870903-4df1664ac249?w=600&q=80',
    'environmental-engineering'         => 'https://images.unsplash.com/photo-1466611653911-95081537e5b7?w=600&q=80',

    'computer-science'                  => 'https://images.unsplash.com/photo-1517694712202-14dd9538aa97?w=600&q=80',
    'information-technology'            => 'https://images.unsplash.com/photo-1451187580459-43490279c0fa?w=600&q=80',
    'software-engineering'              => 'https://images.unsplash.com/photo-1522071820081-009f0129c71c?w=600&q=80',
    'cyber-security'                    => 'https://images.unsplash.com/photo-1563986768609-322da13575f3?w=600&q=80',
    'data-science'                      => 'https://images.unsplash.com/photo-1504868584819-f8e8b4b6d7e3?w=600&q=80',
    'artificial-intelligence'           => 'https://images.unsplash.com/photo-1620712943543-bcc4688e7485?w=600&q=80',
    'information-systems'               => 'https://images.unsplash.com/photo-1460925895917-afdab827c52f?w=600&q=80',

    'medicine-surgery'                  => 'https://images.unsplash.com/photo-1532938911079-1b06ac7ceec7?w=600&q=80',
    'nursing-science'                   => 'https://images.unsplash.com/photo-1576765608535-5f04d1e3f289?w=600&q=80',
    'pharmacy'                          => 'https://images.unsplash.com/photo-1584308666744-24d5c474f2ae?w=600&q=80',
    'medical-laboratory-science'        => 'https://images.unsplash.com/photo-1579154204601-01588f351e67?w=600&q=80',
    'anatomy'                           => 'https://images.unsplash.com/photo-1530497610245-94d3c16cda28?w=600&q=80',
    'physiology'                        => 'https://images.unsplash.com/photo-1559757175-5700dde675bc?w=600&q=80',
    'public-health'                     => 'https://images.unsplash.com/photo-1584515979956-d9f6e5d09982?w=600&q=80',
    'physiotherapy'                     => 'https://images.unsplash.com/photo-1576091160550-2173dba999ef?w=600&q=80',
    'radiography'                       => 'https://images.unsplash.com/photo-1516549655169-df83a0774514?w=600&q=80',
    'dentistry'                         => 'https://images.unsplash.com/photo-1588776814546-1ffcf47267a5?w=600&q=80',

    'accounting'                        => 'https://images.unsplash.com/photo-1554224155-8d04cb21cd6c?w=600&q=80',
    'finance-banking'                   => 'https://images.unsplash.com/photo-1611974789855-9c2a0a7236a3?w=600&q=80',
    'business-administration'           => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=600&q=80',
    'economics'                         => 'https://images.unsplash.com/photo-1590283603385-17ffb3a7f29f?w=600&q=80',
    'marketing'                         => 'https://images.unsplash.com/photo-1533750516457-a7f992034fec?w=600&q=80',
    'mass-communication'                => 'https://images.unsplash.com/photo-1585829365295-ab7cd400c167?w=600&q=80',
    'political-science'                 => 'https://images.unsplash.com/photo-1541872703-74c5e44368f9?w=600&q=80',
    'sociology'                         => 'https://images.unsplash.com/photo-1529156069898-49953e39b3ac?w=600&q=80',
    'psychology'                        => 'https://images.unsplash.com/photo-1507679799987-c73779587ccf?w=600&q=80',
    'public-administration'             => 'https://images.unsplash.com/photo-1497366216548-37526070297c?w=600&q=80',
    'international-relations'           => 'https://images.unsplash.com/photo-1526470608268-f674ce90ebd4?w=600&q=80',
    'law-llb'                           => 'https://images.unsplash.com/photo-1589829545856-d10d557cf95f?w=600&q=80',
    'architecture'                      => 'https://images.unsplash.com/photo-1486406146926-c627a92ad1ab?w=600&q=80',
    'estate-management-valuation'       => 'https://images.unsplash.com/photo-1560518883-ce09059eeffa?w=600&q=80',
];

$teacherAvatars = [

    2 => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&q=80',

    3 => 'https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&q=80',

    'marcus'  => 'https://images.unsplash.com/photo-1531746020798-e6953c6e8e04?w=400&q=80',
    'sarah'   => 'https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&q=80',
    'alex'    => 'https://images.unsplash.com/photo-1507003211169-0a1dd7228f2d?w=400&q=80',
    'michael' => 'https://images.unsplash.com/photo-1500648767791-00dcc994a43e?w=400&q=80',
    'amanda'  => 'https://images.unsplash.com/photo-1580489944761-15a19d654956?w=400&q=80',
    'fatima'  => 'https://images.unsplash.com/photo-1559839734-2b71ea197ec2?w=400&q=80',
];

$courses = $pdo->query("SELECT id, title, slug, thumbnail FROM courses")->fetchAll(PDO::FETCH_ASSOC);
$updatedCoursesCount = 0;

foreach ($courses as $c) {
    $slug = trim($c['slug']);
    $title = strtolower(trim($c['title']));
    $matchedImage = null;

    if (isset($courseImageMap[$slug])) {
        $matchedImage = $courseImageMap[$slug];
    } else {

        foreach ($courseImageMap as $key => $img) {
            $cleanKey = str_replace('-', ' ', $key);
            if (strpos($slug, $key) !== false || strpos($title, $cleanKey) !== false) {
                $matchedImage = $img;
                break;
            }
        }
    }

    if ($matchedImage && $matchedImage !== $c['thumbnail']) {
        $stmt = $pdo->prepare("UPDATE courses SET thumbnail = ? WHERE id = ?");
        $stmt->execute([$matchedImage, $c['id']]);
        $updatedCoursesCount++;
    }
}

$stmtU = $pdo->prepare("UPDATE users SET avatar = ? WHERE id = ?");
$stmtU->execute(['https://images.unsplash.com/photo-1573496359142-b8d87734a5a2?w=400&q=80', 2]);
$stmtU->execute(['https://images.unsplash.com/photo-1534528741775-53994a69daeb?w=400&q=80', 3]);

echo "Database Image Upgrade Summary:\n";
echo "- Updated {$updatedCoursesCount} courses with subject-tailored unique imagery.\n";
echo "- Updated Teacher avatars with distinct high-resolution portraits.\n";
