<?php
if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null, $encoding = null) {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}

require __DIR__ . '/auth_check.php';
require_once __DIR__ . '/ban_helpers.php';

date_default_timezone_set('Europe/London');

$dataDir = __DIR__ . '/_private';
$issuesFile = $dataDir . '/issues.txt';
$deletedMessageLogFile = $dataDir . '/deleted_message_log.txt';
$chatOffencesFile = $dataDir . '/chat_filter_offences.json';
$htaccessFile = $dataDir . '/.htaccess';

$currentUser = $_SESSION['username'] ?? 'Unknown';

// Prevent guest users from submitting reports
if ($currentUser === 'guest') {
  http_response_code(403);
  echo 'Guest users cannot submit reports. Please log in to submit reports.';
  exit;
}

if (!is_dir($dataDir)) {
  mkdir($dataDir, 0755, true);
}

if (!file_exists($htaccessFile)) {
  file_put_contents($htaccessFile, "Require all denied
Deny from all
");
}

if (!file_exists($issuesFile)) {
  file_put_contents($issuesFile, '');
}

if (!file_exists($deletedMessageLogFile)) {
  file_put_contents($deletedMessageLogFile, '');
}

if (!file_exists($chatOffencesFile)) {
  file_put_contents($chatOffencesFile, '{}');
}

function clean_text($text, $maxLength = 500) {
  $text = trim($text);
  $text = str_replace(["
", "
", "|"], ' ', $text);
  $text = preg_replace('/\s+/', ' ', $text);
  return mb_substr($text, 0, $maxLength);
}

$blockedChatTerms = [
  'assfuck',
  'cuntfucker',
  'niggers',
  'niggerhole',
  'nigger',
  'balllicker',
  'nlgger',
  'porchmonkey',
  'Porch-monkey',
  'cunt',
  'asswhore',
  'fuck',
  'assjockey',
  'Dothead',
  'blacks',
  'cumqueen',
  'fatfucker',
  'Jigaboo',
  'jiggabo',
  'nlggor',
  'snownigger',
  'Spearchucker',
  'Timber-nigger',
  'shitnigger',
  'asslick',
  'shithead',
  'asshole',
  'cuntlicker',
  'kunt',
  'spaghettinigger',
  'Towel-head',
  'Chernozhopy',
  'asslicker',
  'Bluegum',
  'twat',
  'ABCD',
  'bitchslap',
  'bulldyke',
  'choad',
  'cumshot',
  'fatass',
  'jigger',
  'kyke',
  'cumskin',
  'asian',
  'asscowboy',
  'assmuncher',
  'banging',
  'Burrhead',
  'Camel-Jockey',
  'coon',
  'crotchrot',
  'cumfest',
  'dicklicker',
  'fag',
  'fagot',
  'felatio',
  'fatfuck',
  'goldenshower',
  'hore',
  'jackoff',
  'jigg',
  'jigga',
  'jizjuice',
  'jizm',
  'jiz',
  'jizzim',
  'kumming',
  'kunilingus',
  'Moolinyan',
  'motherfucking',
  'motherfuckings',
  'phuk',
  'Sheboon',
  'shitforbrains',
  'slanteye',
  'spick',
  'fuuck',
  'antinigger',
  'aperest',
  'Americoon',
  'ABC',
  'Aunt-Jemima',
  'queer',
  'anal',
  'asspirate',
  'addict',
  'bitch',
  'ass',
  'Buddhahead',
  'chode',
  'phuking',
  'phukking',
  'bastard',
  'bulldike',
  'dripdick',
  'assassination',
  'A-rab',
  'Buckra',
  'bootycall',
  'assholes',
  'assbagger',
  'cheesedick',
  'cooter',
  'cum',
  'cumquat',
  'cunnilingus',
  'datnigga',
  'deepthroat',
  'dick',
  'dickforbrains',
  'dickbrain',
  'dickless',
  'dike',
  'diddle',
  'dixiedyke',
  'Eskimo',
  'fannyfucker',
  'fatso',
  'fckcum',
  'Golliwog',
  'Goyim',
  'homobangers',
  'hooters',
  'Indognesial',
  'Indonesial',
  'jew',
  'jijjiboo',
  'knockers',
  'kummer',
  'mothafucka',
  'mooncricket',
  'Moon-Cricket',
  'Oven-Dodger',
  'Peckerwood',
  'phuked',
  'piccaninny',
  'picaninny',
  'phuq',
  'Polock',
  'poorwhitetrash',
  'prick',
  'pu55y',
  'Pshek',
  'slut',
  'jizzum',
  'cunteyed',
  'Spic',
  'Swamp-Guinea',
  'stupidfucker',
  'stupidfuck',
  'titfuck',
  'Twinkie',
  'cock',
  'Abeed',
  'analannie',
  'asshore',
  'Beaner',
  'Bootlip',
  'Burr-head',
  'buttfucker',
  'butt-fucker',
  'Uncle-Tom',
  'cocksmoker',
  'Africoon',
  'AmeriKKKunt',
  'antifaggot',
  'assklown',
  'asspuppies',
  'blackman',
  'jism',
  'blumpkin',
  'retard',
  'Gringo',
  'douchebag',
  'Piefke',
  'areola',
  'backdoorman',
  'Abbie',
  'bigbutt',
  'buttface',
  'cumbubble',
  'cumming',
  'Dego',
  'dong',
  'doggystyle',
  'doggiestyle',
  'erection',
  'feces',
  'goddamned',
  'gonzagas',
  'Greaser',
  'Greaseball',
  'handjob',
  'Half-breed',
  'horney',
  'jihad',
  'kumquat',
  'Lebo',
  'Moskal',
  'Mountain-Turk',
  'nofuckingway',
  'orgies',
  'orgy',
  'pecker',
  'poontang',
  'poon',
  'Polentone',
  'pu55i',
  'shitfuck',
  'shiteater',
  'shitdick',
  'sluts',
  'slutt',
  'Mangal',
  'Hymie',
  'stiffy',
  'titfucker',
  'twink',
  'asspacker',
  'barelylegal',
  'Bozgor',
  'bumfuck',
  'shit for brains',
  'butchdyke',
  'butt-fuckers',
  'buttpirate',
  'cameljockey',
  'Carcamano',
  'Chankoro',
  'Choc-ice',
  'Chug',
  'Ciapaty-or-ciapak',
  'Cina',
  'cocksucer',
  'crackwhore',
  'Bougnoule',
  'unfuckable',
  'Africoon-Americoon',
  'Africoonia',
  'Americunt',
  'apesault',
  'Assburgers',
  'fucktardedness',
  'sheepfucker',
  'Wuhan-virus',
  'Wetback',
  'Aseng',
  'bumblefuck',
  'fastfuck',
  'itch',
  'nizzle',
  'Oriental',
  'cisgender',
  'ballsack',
  'penis',
  'zigabo',
  'Bule',
  'breastman',
  'bountybar',
  'Bounty-bar',
  'bondage',
  'bombing',
  'bullshit',
  'asses',
  'cancer',
  'cunilingus',
  'cummer',
  'dicklick',
  'ejaculation',
  'faeces',
  'fairy',
  'hoes',
  'idiot',
  'Laowai',
  'Leb',
  'muff',
  'muffdive',
  'Oreo',
  'orgasm',
  'orgasim',
  'osama',
  'peepshow',
  'Petrol-sniffer',
  'perv',
  'prickhead',
  'shitfit',
  'spermbag',
  'suckmytit',
  'suckmydick',
  'suckmyass',
  'suckme',
  'suckdick',
  'Yuon',
  'motherfucker',
  'groe',
  'Ali Baba',
  'retarded',
  'assfucker',
  'assmunch',
  'assranger',
  'Ayrab',
  'assclown',
  'buttfuck',
  'butt-fuck',
  'buttman',
  'Chink',
  'cocksucker',
  'cooly',
  'Coon-ass',
  'crotchmonkey',
  'Bohunk',
  'cockcowboy',
  'cocksmith',
  'catfucker',
  'fucktardedly',
  'trans-testicle',
  'Wigger',
  'whiskeydick',
  'aboriginal',
  'asskisser',
  'whitelist',
  'Latinx',
  'yambag',
  'boob',
  'beef curtains',
  'clunge',
  'af',
  'wokeness',
  'bitchez',
  'Iceberg Fuckers',
  'Zhyd',
  'bellend',
  'arsehole',
  'tatas',
  'assassinate',
  'boonga',
  'booby',
  'bullcrap',
  'defecate',
  'Dhoti',
  'dope',
  'hobo',
  'bigass',
  'hussy',
  'illegal',
  'ky',
  'moneyshot',
  'molestor',
  'nooner',
  'nookie',
  'nookey',
  'Paleface',
  'pansy',
  'peehole',
  'phonesex',
  'period',
  'pornking',
  'pornflick',
  'porn',
  'pooper',
  'sexwhore',
  'shitface',
  'shit',
  'slav',
  'slimeball',
  'sniggers',
  'snowback',
  'spermherder',
  'spankthemonkey',
  'spitter',
  'strapon',
  'Tacohead',
  'suckoff',
  'titbitnipply',
  'Turco-Albanian',
  'tranny',
  'trannie',
  'zhidovka',
  'zhid',
  'Bakra',
  'Afro engineering',
  'Ah Chah',
  'alligatorbait',
  'arabs',
  'Arabush',
  'Ashke-Nazi',
  'assblaster',
  'assmonkey',
  'badfuck',
  'bazongas',
  'beatoff',
  'bazooms',
  'Balija',
  'bunghole',
  'butchdike',
  'buttfuckers',
  'Boche',
  'buttbang',
  'butt-bang',
  'buttmunch',
  'Charlie',
  'chav',
  'Chinaman',
  'coloured',
  'boong',
  'butchbabes',
  'clit',
  'cockknob',
  'cocksucking',
  'cocktease',
  'Cokin',
  'anchor-baby',
  'cumsock',
  'fisting',
  'fuck-you',
  'Fritzie',
  'transgendered',
  'White-trash',
  'whitetrash',
  'whop',
  'wtf',
  'Vatnik',
  'welfare queen',
  'assman',
  'black',
  'Gyopo',
  'goddam',
  'minge',
  'punani',
  'douche',
  'doofus',
  'munter',
  'moron',
  'ballgag',
  'femsplaining',
  'asslover',
  'looney',
  'fat',
  'homosexual',
  'turd',
  'zhydovka',
  'effing',
  'minger',
  'dullard',
  'buggery',
  'brea5t',
  'addicted',
  'demon',
  'devilworshipper',
  'deth',
  'destroy',
  'doo-doo',
  'doodoo',
  'escort',
  'farting',
  'fairies',
  'husky',
  'incest',
  'Hunky',
  'jiggy',
  'laid',
  'molester',
  'Mzungu',
  'nigglings',
  'niggling',
  'niggles',
  'pee-pee',
  'pi55',
  'phungky',
  'porno',
  'pooping',
  'prostitute',
  'pros',
  'sexslave',
  'sextogo',
  'shag',
  'shithappens',
  'shithapens',
  'shitfull',
  'shitcan',
  'shinola',
  'slavedriver',
  'sleezeball',
  'spermhearder',
  'swastika',
  'shits',
  'trots',
  'trisexual',
  'twobitwhore',
  'Munt',
  'gangsta',
  'Abo',
  'addicts',
  'Alligator bait',
  'analsex',
  'Redskin',
  'Gypsy',
  'Ang mo',
  'Ape',
  'arab',
  'Aravush',
  'Armo',
  'arse',
  'asswipe',
  'Beaney',
  'beatyourmeat',
  'bigbastard',
  'bitches',
  'Bogtrotter',
  'bung',
  'beaver',
  'bestial',
  'bogan',
  'Cabbage-Eater',
  'carpetmuncher',
  'carruth',
  'cocklover',
  'cockrider',
  'cornhole',
  'bollock',
  'Bog-Irish',
  'chinamen',
  'clamdigger',
  'clamdiver',
  'dwarf',
  'cakewalk',
  'ftw',
  'fml',
  'handicapped',
  'cawk',
  'carpet-muncher',
  'fuzzy-headed',
  'full-blood',
  'fuckity-bye',
  'frogess',
  'Norte',
  'troid',
  'willy',
  'pud',
  'pubiclice',
  'whitewashing',
  'Brit',
];

function normalize_filter_text($text) {
  $text = mb_strtolower((string)$text, 'UTF-8');

  // Replace common leetspeak/symbol bypasses before removing punctuation.
  // This catches things like n1gga, n!gga, n.i.g.g.a, f-u-c-k, etc.
  $text = strtr($text, [
    '0' => 'o',
    '1' => 'i',
    '!' => 'i',
    '|' => 'i',
    '¡' => 'i',
    '3' => 'e',
    '4' => 'a',
    '@' => 'a',
    '5' => 's',
    '$' => 's',
    '7' => 't',
    '+' => 't',
    '8' => 'b',
    '9' => 'g',
    '6' => 'g',
    '(' => 'c',
    '<' => 'c'
  ]);

  // Remove spaces, punctuation, hyphens, dots, underscores, emojis, etc.
  $text = preg_replace('/[^a-z0-9]+/iu', '', $text);

  // Collapse repeated letters so nniigggaa still becomes nigga-ish.
  $text = preg_replace('/([a-z0-9])\1{2,}/u', '$1$1', $text);

  return $text ?? '';
}

function chat_filter_offence_count($offencesFile, $username) {
  $key = strtolower(clean_text($username, 40));
  if ($key === '') return 0;

  $offences = json_decode(@file_get_contents($offencesFile), true);
  if (!is_array($offences)) $offences = [];

  return (int)($offences[$key]['count'] ?? 0);
}

function add_chat_filter_offence($offencesFile, $username, $blockedTerm, $message) {
  $key = strtolower(clean_text($username, 40));
  if ($key === '') return 1;

  $offences = json_decode(@file_get_contents($offencesFile), true);
  if (!is_array($offences)) $offences = [];

  $currentCount = (int)($offences[$key]['count'] ?? 0);
  $newCount = $currentCount + 1;

  $offences[$key] = [
    'name' => clean_text($username, 40),
    'count' => $newCount,
    'last_blocked_term' => clean_text($blockedTerm, 120),
    'last_message' => clean_text($message, 500),
    'last_offence_at' => time(),
    'last_offence_at_text' => date('Y-m-d H:i:s')
  ];

  file_put_contents($offencesFile, json_encode($offences, JSON_PRETTY_PRINT), LOCK_EX);
  return $newCount;
}

function message_contains_blocked_term($message, $blockedTerms) {
  $rawMessage = mb_strtolower((string)$message, 'UTF-8');
  $normalizedMessage = normalize_filter_text($message);

  // Extra serious terms to catch bypasses like n1gga.
  $extraBlockedTerms = [
    'nigga',
    'niggah',
    'niga',
    'niger'
  ];

  // Words that were too broad / normal and caused false bans, like "af" in "after".
  // Keep actual slurs blocked, but do not ban people for normal school/chat words.
  $safeTerms = [
    'af', 'abc', 'abcd', 'asian', 'black', 'blacks', 'cisgender', 'latinx',
    'transgendered', 'homosexual', 'period', 'cancer', 'fat', 'brit', 'addict',
    'addicted', 'addicts', 'illegal', 'dwarf', 'hobo', 'demon', 'devilworshipper',
    'wokeness', 'whitelist', 'oriental'
  ];

  foreach (array_merge($blockedTerms, $extraBlockedTerms) as $term) {
    $term = trim(mb_strtolower((string)$term, 'UTF-8'));
    if ($term === '' || $term === 'add-slur-here' || $term === 'another-banned-word-here') {
      continue;
    }

    if (in_array($term, $safeTerms, true)) {
      continue;
    }

    $normalizedTerm = normalize_filter_text($term);
    if ($normalizedTerm === '' || strlen($normalizedTerm) <= 2) {
      continue;
    }

    // Exact word/phrase match first. This catches capitals and punctuation around the word.
    $normalPattern = '/(?<![a-z0-9])' . preg_quote($term, '/') . '(?![a-z0-9])/iu';
    if (preg_match($normalPattern, $rawMessage)) {
      return $term;
    }

    // Bypass match for serious longer terms only.
    // This catches n1gga, n.i.g.g.a, f-u-c-k, etc.
    // It intentionally avoids tiny terms like "af" so words like "after" do not trigger bans.
    if (strlen($normalizedTerm) >= 4 && strpos($normalizedMessage, $normalizedTerm) !== false) {
      return $term;
    }
  }

  return false;
}



$issue = clean_text($_POST['issue'] ?? '', 500);

if ($issue === '') {
  http_response_code(400);
  echo 'Missing issue.';
  exit;
}

$blockedTerm = message_contains_blocked_term($issue, $blockedChatTerms);

if ($blockedTerm !== false) {
  $offenceCount = add_chat_filter_offence($chatOffencesFile, $currentUser, $blockedTerm, $issue);
  $banMinutes = max(1, $offenceCount * 5);
  $banSeconds = $banMinutes * 60;

  save_deleted_chat_log($currentUser, $issue, 'Auto ' . $banMinutes . ' minute ban for blocked report/request term: ' . $blockedTerm);

  set_account_ban(
    $currentUser,
    $banSeconds,
    'You are banned for ' . $banMinutes . ' minutes for using a banned word/slur in a report or request.',
    'report-filter',
    [
      'reason' => 'blocked_report_request_term',
      'source' => 'report-filter',
      'blocked_term' => $blockedTerm,
      'chat_filter_offence_count' => $offenceCount,
      'ban_minutes' => $banMinutes,
      'blocked_message' => clean_text($issue, 500)
    ]
  );

  http_response_code(403);
  echo 'Blocked word detected. You have been banned.';
  exit;
}

$logLine = date('Y-m-d H:i:s') . ' | User: ' . clean_text($currentUser, 40) . ' | ' . $issue . PHP_EOL;
file_put_contents($issuesFile, $logLine, FILE_APPEND | LOCK_EX);

echo 'Saved';
?>
