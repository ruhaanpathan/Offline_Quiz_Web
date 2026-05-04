<?php
$pageTitle = 'Create Quiz';
require_once __DIR__ . '/../includes/auth_check.php';
requireTeacher();
require_once __DIR__ . '/../config/db.php';

$tid = getCurrentUserId();
$classId = (int)($_GET['class_id'] ?? 0);

// Verify class
$stmt = $pdo->prepare("SELECT * FROM classes WHERE id = ? AND teacher_id = ?");
$stmt->execute([$classId, $tid]);
$class = $stmt->fetch();
if (!$class) { setFlash('error', 'Class not found.'); redirect('/teacher/classes.php'); }

// Get existing topics
$topicsStmt = $pdo->prepare("SELECT * FROM topics WHERE class_id = ? ORDER BY topic_name");
$topicsStmt->execute([$classId]);
$existingTopics = $topicsStmt->fetchAll();

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = trim($_POST['title'] ?? '');
    $questions = $_POST['questions'] ?? [];
    
    if (!$title || empty($questions)) {
        setFlash('error', 'Quiz title and at least one question are required.');
        redirect("/teacher/create_quiz.php?class_id=$classId");
    }

    $pdo->beginTransaction();
    try {
        $code = generateQuizCode($pdo);
        $ins = $pdo->prepare("INSERT INTO quizzes (class_id, teacher_id, quiz_code, title, status) VALUES (?, ?, ?, ?, 'draft')");
        $ins->execute([$classId, $tid, $code, $title]);
        $quizId = $pdo->lastInsertId();

        foreach ($questions as $order => $q) {
            $topicName = trim($q['topic'] ?? '');
            if (!$topicName) continue;
            
            // Get or create topic
            $tCheck = $pdo->prepare("SELECT id FROM topics WHERE topic_name = ? AND class_id = ?");
            $tCheck->execute([$topicName, $classId]);
            $topic = $tCheck->fetch();
            if ($topic) {
                $topicId = $topic['id'];
            } else {
                $tIns = $pdo->prepare("INSERT INTO topics (topic_name, class_id) VALUES (?, ?)");
                $tIns->execute([$topicName, $classId]);
                $topicId = $pdo->lastInsertId();
            }

            $timeLimit = (int)($q['time_limit'] ?? 30);
            $qIns = $pdo->prepare("INSERT INTO questions (quiz_id, topic_id, question_text, option_a, option_b, option_c, option_d, correct_option, time_limit_seconds, question_order) VALUES (?,?,?,?,?,?,?,?,?,?)");
            $qIns->execute([
                $quizId, $topicId,
                trim($q['text']),
                trim($q['option_a']), trim($q['option_b']),
                trim($q['option_c']), trim($q['option_d']),
                $q['correct'], $timeLimit, $order + 1
            ]);
        }

        $pdo->commit();
        setFlash('success', "Quiz '$title' created! Code: $code");
        redirect("/teacher/host_quiz.php?id=$quizId");
    } catch (Exception $e) {
        $pdo->rollBack();
        setFlash('error', 'Error creating quiz: ' . $e->getMessage());
        redirect("/teacher/create_quiz.php?class_id=$classId");
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="page-wrapper animate-fade">
    <div class="page-header">
        <div>
            <h1>Create Quiz</h1>
            <div class="breadcrumb"><a href="/teacher/dashboard.php">Dashboard</a> / <a href="/teacher/class_detail.php?id=<?= $classId ?>"><?= sanitize($class['class_name']) ?></a> / Create Quiz</div>
        </div>
    </div>

    <form method="POST" id="quizForm">
        <div class="card" style="margin-bottom:24px;">
            <div class="form-group">
                <label>Quiz Title</label>
                <input type="text" name="title" class="form-control" placeholder="e.g. Lecture 5 — Sorting Algorithms" required>
            </div>
        </div>

        <div id="questionsContainer"></div>

        <div style="display:flex;gap:12px;margin-top:20px;">
            <button type="button" class="btn btn-outline btn-lg" onclick="addQuestion()">+ Add Question</button>
            <button type="submit" class="btn btn-primary btn-lg" id="submitBtn" disabled>Create Quiz</button>
        </div>
    </form>
</div>

<script>
const existingTopics = <?= json_encode(array_column($existingTopics, 'topic_name')) ?>;
let qCount = 0;

function addQuestion() {
    qCount++;
    const idx = qCount - 1;
    const topicOptions = existingTopics.map(t => `<option value="${t}">${t}</option>`).join('');
    
    const html = `
    <div class="card" style="margin-bottom:16px;" id="q_${idx}">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
            <h3 style="font-size:1rem;">Question ${qCount}</h3>
            <button type="button" class="btn btn-outline btn-sm" style="color:var(--accent-red);" onclick="removeQuestion(${idx})">Remove</button>
        </div>
        <div class="form-row">
            <div class="form-group" style="flex:2;">
                <label>Topic</label>
                <input type="text" name="questions[${idx}][topic]" class="form-control" list="topicList" placeholder="Type or select a topic" required>
            </div>
            <div class="form-group" style="flex:1;">
                <label>Time (seconds)</label>
                <input type="number" name="questions[${idx}][time_limit]" class="form-control" value="30" min="10" max="120">
            </div>
        </div>
        <div class="form-group">
            <label>Question Text</label>
            <textarea name="questions[${idx}][text]" class="form-control" rows="2" placeholder="Enter your question here..." required></textarea>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Option A</label>
                <input type="text" name="questions[${idx}][option_a]" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Option B</label>
                <input type="text" name="questions[${idx}][option_b]" class="form-control" required>
            </div>
        </div>
        <div class="form-row">
            <div class="form-group">
                <label>Option C</label>
                <input type="text" name="questions[${idx}][option_c]" class="form-control" required>
            </div>
            <div class="form-group">
                <label>Option D</label>
                <input type="text" name="questions[${idx}][option_d]" class="form-control" required>
            </div>
        </div>
        <div class="form-group">
            <label>Correct Answer</label>
            <select name="questions[${idx}][correct]" class="form-control" required>
                <option value="">Select correct option</option>
                <option value="a">A</option><option value="b">B</option>
                <option value="c">C</option><option value="d">D</option>
            </select>
        </div>
    </div>`;
    
    document.getElementById('questionsContainer').insertAdjacentHTML('beforeend', html);
    document.getElementById('submitBtn').disabled = false;
}

function removeQuestion(idx) {
    document.getElementById('q_' + idx)?.remove();
    if (!document.getElementById('questionsContainer').children.length) {
        document.getElementById('submitBtn').disabled = true;
    }
}

// Start with 1 question
addQuestion();
</script>

<datalist id="topicList">
    <?php foreach ($existingTopics as $t): ?>
    <option value="<?= sanitize($t['topic_name']) ?>">
    <?php endforeach; ?>
</datalist>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
