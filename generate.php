<?php
header("Content-Type: application/json");

$OPENAI_API_KEY = "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";

$input = json_decode(file_get_contents("php://input"), true);

$prompt = "
Buatkan template pesan WhatsApp:
- Tujuan: {$input['tujuan']}
- Nama customer: {$input['nama']}
- Nama perusahaan: {$input['perusahaan']}
- Gaya bahasa: {$input['gaya']}
- Bahasa Indonesia
- Sopan dan siap kirim
";

$data = [
  "model" => "gpt-4.1-mini",
  "messages" => [
    ["role" => "user", "content" => $prompt]
  ],
  "temperature" => 0.7
];

$ch = curl_init("https://api.openai.com/v1/chat/completions");
curl_setopt_array($ch, [
  CURLOPT_POST => true,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Content-Type: application/json",
    "Authorization: Bearer $OPENAI_API_KEY"
  ],
  CURLOPT_POSTFIELDS => json_encode($data)
]);

$response = curl_exec($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
  echo json_encode(["error" => $error]);
  exit;
}

$result = json_decode($response, true);

echo json_encode([
  "result" => $result['choices'][0]['message']['content'] ?? "Gagal generate"
]);
