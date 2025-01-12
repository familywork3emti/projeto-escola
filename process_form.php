
<?php
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PHPMailer\PHPMailer\SMTP;

require 'vendor/autoload.php';

// Configurações
define('DB_HOST', 'localhost');
define('DB_USER', 'seu_usuario');
define('DB_PASS', 'sua_senha');
define('DB_NAME', 'escola_guerino');

define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USER', 'seu_email@gmail.com');
define('SMTP_PASS', 'sua_senha_app');
define('EMAIL_ESCOLA', 'gilsondasilva182@gmail.com');

header('Content-Type: application/json');

try {
    // Validar método da requisição
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        throw new Exception('Método inválido');
    }

    // Validar e sanitizar inputs
    $nome = filter_var($_POST['name'] ?? '', FILTER_SANITIZE_STRING);
    $email = filter_var($_POST['email'] ?? '', FILTER_SANITIZE_EMAIL);
    $mensagem = filter_var($_POST['message'] ?? '', FILTER_SANITIZE_STRING);

    if (empty($nome) || strlen($nome) < 2) {
        throw new Exception('Nome inválido');
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Email inválido');
    }

    if (empty($mensagem) || strlen($mensagem) < 10) {
        throw new Exception('Mensagem muito curta');
    }

    // Conectar ao banco de dados
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );

    // Salvar mensagem no banco
    $stmt = $pdo->prepare("
        INSERT INTO mensagens (nome, email, mensagem, data_criacao, email_enviado)
        VALUES (?, ?, ?, NOW(), 0)
    ");
    
    $stmt->execute([$nome, $email, $mensagem]);
    $mensagemId = $pdo->lastInsertId();

    // Configurar e enviar email
    $mail = new PHPMailer(true);

    // Configurações do servidor
    $mail->isSMTP();
    $mail->Host = SMTP_HOST;
    $mail->SMTPAuth = true;
    $mail->Username = SMTP_USER;
    $mail->Password = SMTP_PASS;
    $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    $mail->Port = SMTP_PORT;
    $mail->CharSet = 'UTF-8';

    // Destinatários
    $mail->setFrom(SMTP_USER, 'Sistema Escola');
    $mail->addAddress(EMAIL_ESCOLA, 'Escola');
    $mail->addReplyTo($email, $nome);

    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = 'Nova mensagem do site da escola';
    
    $corpoHTML = "
    <html>
    <body style='font-family: Arial, sans-serif;'>
        <h2>Nova mensagem recebida do site da escola</h2>
        <p><strong>Nome:</strong> {$nome}</p>
        <p><strong>Email:</strong> {$email}</p>
        <p><strong>Data:</strong> " . date('d/m/Y H:i:s') . "</p>
        <div style='background-color: #f5f5f5; padding: 15px; border-radius: 5px;'>
            <p><strong>Mensagem:</strong></p>
            <p>" . nl2br(htmlspecialchars($mensagem)) . "</p>
        </div>
    </body>
    </html>";

    $mail->Body = $corpoHTML;
    $mail->AltBody = strip_tags(str_replace('<br>', "\n", $corpoHTML));

    $mail->send();

    // Atualizar status no banco
    $stmt = $pdo->prepare("UPDATE mensagens SET email_enviado = 1 WHERE id = ?");
    $stmt->execute([$mensagemId]);

    // Resposta de sucesso
    echo json_encode([
        'status' => 'success',
        'message' => 'Mensagem enviada com sucesso! Entraremos em contato em breve.'
    ]);

} catch (Exception $e) {
    // Log do erro
    error_log("Erro no formulário de contato: " . $e->getMessage());
    
    // Resposta de erro
    http_response_code(400);
    echo json_encode([
        'status' => 'error',
        'message' => 'Erro ao enviar mensagem. Por favor, tente novamente.'
    ]);
} finally {
    // Fechar conexões
    $pdo = null;
    $stmt = null;
}
?>