<?php
require_once __DIR__ . "/lib/env.php";
env_load();
header("Content-Type: application/json");
$month = isset($_GET["month"]) ? $_GET["month"] : date("Y-m");
$start = DateTime::createFromFormat("Y-m-d", $month . "-01")->format("Y-m-01");
$end = DateTime::createFromFormat("Y-m-d", $month . "-01")->format("Y-m-t");
$supabaseUrl = getenv("SUPABASE_URL");
$supabaseKey = getenv("SUPABASE_ANON_KEY") ?: getenv("SUPABASE_PUBLISHABLE_KEY");
function getJson($url, $headers) {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  $resp = curl_exec($ch);
  $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  if ($code >= 200 && $code < 300) return json_decode($resp, true);
  return null;
}
if ($supabaseUrl && $supabaseKey) {
  $base = rtrim($supabaseUrl, "/") . "/rest/v1";
  $headers = [
    "apikey: " . $supabaseKey,
    "Authorization: Bearer " . $supabaseKey,
    "Accept: application/json"
  ];
  $teachers = getJson($base . "/teachers?select=id,name,level,subject", $headers);
  $att = getJson($base . "/attendance?select=teacher_id,date,status&date=gte." . $start . "&date=lte." . $end, $headers);
  $byTeacher = [];
  if (is_array($att)) {
    foreach ($att as $a) {
      $tid = $a["teacher_id"];
      if (!isset($byTeacher[$tid])) $byTeacher[$tid] = ["present" => 0, "absent" => 0];
      $s = strtolower($a["status"]);
      if ($s === "present") $byTeacher[$tid]["present"] += 1;
      else if ($s === "absent") $byTeacher[$tid]["absent"] += 1;
    }
  }
  $out = [];
  if (is_array($teachers)) {
    foreach ($teachers as $t) {
      $tid = $t["id"];
      $p = $byTeacher[$tid]["present"] ?? 0;
      $a = $byTeacher[$tid]["absent"] ?? 0;
      $total = $p + $a;
      $rate = $total > 0 ? ($p / $total) : 0;
      $out[] = [
        "name" => $t["name"],
        "level" => $t["level"],
        "subject" => $t["subject"] ?? "",
        "present" => $p,
        "absent" => $a,
        "rate" => $rate
      ];
    }
  }
  echo json_encode($out);
  exit;
}
$sample = [
  ["name" => "Alice Ramos", "level" => "Elementary", "subject" => "Math", "present" => 18, "absent" => 2],
  ["name" => "Ben Cruz", "level" => "Elementary", "subject" => "Science", "present" => 17, "absent" => 3],
  ["name" => "Cara Lim", "level" => "Elementary", "subject" => "English", "present" => 20, "absent" => 0],
  ["name" => "Diego Santos", "level" => "High School", "subject" => "Physics", "present" => 19, "absent" => 1],
  ["name" => "Ella Tan", "level" => "High School", "subject" => "Chemistry", "present" => 16, "absent" => 4],
  ["name" => "Fynn Ong", "level" => "High School", "subject" => "History", "present" => 18, "absent" => 2]
];
foreach ($sample as &$s) {
  $total = $s["present"] + $s["absent"];
  $s["rate"] = $total > 0 ? ($s["present"] / $total) : 0;
}
echo json_encode($sample);
