<?php
$options = getopt("e:");

$email = $options['e'];

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo "E-mail is invalid or parameter -e not given...\n";
  exit;
};

$sql_user = "admin";
$sql_pass = `cat /etc/psa/.psa.shadow`;
$sql_host = "localhost";

$dsn = "mysql:host=$sql_host;dbname=roundcubemail";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];
try {
     $pdo = new PDO($dsn, $sql_user, $sql_pass, $options);
} catch (\PDOException $e) {
     throw new \PDOException($e->getMessage(), (int)$e->getCode());
}

// Get User ID
$stmt = $pdo->prepare('SELECT * FROM users WHERE username = :email');
$stmt->execute(['email' => $email]);
$result = $stmt->fetch();
$user_id = $result['user_id'];

if ($user_id == NULL) {
  throw new Exception("E-mail $email not found in roundcube database");
}

// Get contactgroups
$stmt = $pdo->prepare('SELECT * FROM contactgroups WHERE user_id = :uid');
$stmt->execute(['uid' => $user_id]);
$groups = $stmt->fetchAll();

$group_ids = "";
foreach($groups as $group) {
  $group_ids[] = $group['contactgroup_id'];
};

// Get contacts
$stmt = $pdo->prepare('SELECT * FROM contacts WHERE user_id = :uid');
$stmt->execute(['uid' => $user_id]);
$contacts = $stmt->fetchAll();

// Get members
$stmt = $pdo->prepare('SELECT * FROM contactgroupmembers WHERE contactgroup_id IN (:gid)');
$stmt->execute(['gid' => implode(",", $group_ids)]);
$members = $stmt->fetchAll();

// Dump VCARDs
foreach($contacts as $contact) {
  $vcard = $contact['vcard'];
  $vcard_new = str_replace("END:VCARD", "", $vcard);

  $categories = array();
  // Get membership
  foreach($members as $member) {
    if ($contact['contact_id'] == $member['contact_id']) {
      foreach($groups as $group) {
        if ($group['contactgroup_id'] == $member['contactgroup_id']) {
          $categories[] = $group['name'];
        };
      };
    };
  };

  if (count($categories) > 0) { 
    $vcard_new .= "CATEGORIES:" . implode(",", $categories) . "\n";
  }

  $vcard_new .= "END:VCARD\n";
  echo $vcard_new;
};
