<?php
/*
Plugin Name:  IFeelQuizzy
Plugin URI:   http://ifeelquizzy.local/
Description:  A plugin for creating quizzes with multible answers and a point system for calcutaing percentage answers. 
Version:      1.0
Author:       Peppic
Author URI:   http://ifeelquizzy.local/
License:      GPL2
License URI:  https://www.gnu.org/licenses/gpl-2.0.html
Text Domain:  ifeelquizzy
Domain Path:  /
*/

function ifeelquizzy_install()
{
    global $wpdb;

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');

    $charset_collate = $wpdb->get_charset_collate();

    // Create characters table
    $characters = $wpdb->prefix . 'characters';
    $characters_sql = "CREATE TABLE $characters (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        description text NOT NULL,
        image varchar(255) DEFAULT '' NOT NULL,
        quiz_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($characters_sql);

    // Create quizzes table
    $quizzes = $wpdb->prefix . 'quizzes';
    $quizzes_sql = "CREATE TABLE $quizzes (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        name varchar(255) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($quizzes_sql);

    // Create questions table
    $questions = $wpdb->prefix . 'questions';
    $questions_sql = "CREATE TABLE $questions (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        question_text text NOT NULL,
        quiz_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($questions_sql);

    // Create questions table
    $answers = $wpdb->prefix . 'answers';
    $answers_sql = "CREATE TABLE $answers (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        answer varchar(255) NOT NULL,
        question_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($answers_sql);

    // Create points table
    $points = $wpdb->prefix . 'points';
    $points_sql = "CREATE TABLE $points (
        id mediumint(9) NOT NULL AUTO_INCREMENT,
        points int NOT NULL,
        character_id mediumint(9) NOT NULL,
        answer_id mediumint(9) NOT NULL,
        PRIMARY KEY  (id)
    ) $charset_collate;";
    dbDelta($points_sql);
}

register_activation_hook(__FILE__, 'ifeelquizzy_install');

function ifeelquizzy_enqueue_styles()
{
    wp_enqueue_style('ifeelquizzy-style', plugins_url('style.css', __FILE__));
}
add_action('admin_enqueue_scripts', 'ifeelquizzy_enqueue_styles');
add_action('rest_api_init', function () {
    register_rest_route('ifeelquizzy/v1', '/data/(?P<id>[0-9-]+)', array(
        'methods' => 'GET',
        'callback' => 'get_quizz_data',
    ));
});

function get_quizz_data($data)
{
    global $wpdb;
    $id = $data['id']; // Get the name from the URL

    // Fetch quiz, characters, questions, and answers from database
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}quizzes WHERE id = %d", $id));
    $characters = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}characters WHERE quiz_id = %d", $quiz->id));
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}questions WHERE quiz_id = %d", $quiz->id));

    // Fetch answers and points for each question
    foreach ($questions as $question) {
        $question->answers = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}answers WHERE question_id = %d", $question->id));
        foreach ($question->answers as $answer) {
            $answer->points = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}points WHERE answer_id = %d", $answer->id));
        }
    }

    return array(
        'quiz' => $quiz,
        'characters' => $characters,
        'questions' => $questions
    );
}
// Create admin menu page
add_action('admin_menu', 'ifeelquizzy_menu');
function ifeelquizzy_menu()
{
    add_menu_page('IFeelQuizzy Form', 'IFeelQuizzy', 'manage_options', 'ifeelquizzy', 'ifeelquizzy_admin_page');

    add_submenu_page('ifeelquizzy', 'Modify Quiz', 'Modify Quiz', 'manage_options', 'ifeelquizzy_modify', 'ifeelquizzy_modify_page');

    add_submenu_page('ifeelquizzy delete', 'Delete Quiz', 'Delete Quiz', 'manage_options', 'ifeelquizzy_delete', 'ifeelquizzy_delete_page');
}

add_action('admin_post_ifeelquizzy_create_quiz', 'handle_ifeelquizzy_create_quiz');
function handle_ifeelquizzy_create_quiz()
{
    // Call the createQuizz() function
    $quiz_id = createQuizz();

    // Redirect to the modify page
    wp_redirect(admin_url('admin.php?page=ifeelquizzy_modify&id=' . $quiz_id));
    exit;
}

add_action('admin_post_ifeelquizzy_update_quiz', 'handle_ifeelquizzy_update_quiz');
function handle_ifeelquizzy_update_quiz()
{
    $quiz_id = updateQuizz();

    // Redirect to the modify page
    wp_redirect(admin_url('admin.php?page=ifeelquizzy_modify&id=' . $quiz_id));
    exit;
}

add_action('admin_post_ifeelquizzy_delete_quiz', 'handle_ifeelquizzy_delete_quiz');
function handle_ifeelquizzy_delete_quiz()
{
    deleteQuizz();

    // Redirect to the modify page
    wp_redirect(admin_url('admin.php?page=ifeelquizzy'));
    exit;
}

// Display form on admin page
function ifeelquizzy_admin_page()
{
    global $wpdb;
    $table_name = $wpdb->prefix . 'quizzes'; // replace with your table name

    // Pagination settings
    $per_page = 10;
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($current_page - 1) * $per_page;

    // Get quizzes
    $quizzes = $wpdb->get_results($wpdb->prepare("SELECT * FROM $table_name LIMIT %d OFFSET %d", $per_page, $offset));

    // Get total quizzes
    $total_quizzes = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");

    echo '<div class="wrap">';
    echo '<h2>Quizzes</h2>';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th>ID</th><th>Name</th><th>Actions</th></tr></thead>';
    echo '<tbody>';
    foreach ($quizzes as $quiz) {
        echo '<tr>';
        echo '<td>' . $quiz->id . '</td>'; // display quiz id
        echo '<td>' . $quiz->name . '</td>'; // replace 'name' with your column name
        echo '<td>';
        echo '<a href="' . admin_url('admin.php?page=ifeelquizzy_modify&id=' . $quiz->id) . '">Modify</a> |';
        echo '<form class="delete-form" method="post" action="' . admin_url("admin-post.php") . '" enctype="multipart/form-data">
        <input type="hidden" name="action" value="ifeelquizzy_delete_quiz"><input type="hidden" name="quiz_id" value="' . $quiz->id . '"><input class="delete-btn" type="submit" value="Delete" /></form>';
        echo '</td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';

    // Pagination links
    $total_pages = ceil($total_quizzes / $per_page);
    echo '<div class="tablenav bottom">';
    echo '<div class="tablenav-pages">';
    echo '<span class="displaying-num">' . $total_quizzes . ' items</span>';
    echo '<span class="pagination-links">';
    if ($current_page > 1) {
        echo '<a class="prev-page" href="' . add_query_arg('paged', $current_page - 1) . '"><span class="screen-reader-text">Previous page</span><span aria-hidden="true">‹</span></a>';
    }
    echo '<span class="paging-input">' . $current_page . ' of <span class="total-pages">' . $total_pages . '</span></span>';
    if ($current_page < $total_pages) {
        echo '<a class="next-page" href="' . add_query_arg('paged', $current_page + 1) . '"><span class="screen-reader-text">Next page</span><span aria-hidden="true">›</span></a>';
    }
    echo '</span>';
    echo '</div>';
    echo '</div>';

    echo '<a class="button button-primary" href="' . admin_url('admin.php?page=ifeelquizzy_create') . '">Create New Quiz</a>';
    echo '</div>';
}

// Register settings
add_action('admin_init', 'ifeelquizzy_settings');

function ifeelquizzy_settings()
{
    register_setting('ifeelquizzy-settings', 'ifeelquizzy_field');
}

// Create admin submenu page
add_action('admin_menu', 'ifeelquizzy_submenu');

function ifeelquizzy_submenu()
{
    add_submenu_page('ifeelquizzy', 'Create Quiz', 'Create Quiz', 'manage_options', 'ifeelquizzy_create', 'ifeelquizzy_create_page');
}

function createQuizz()
{
    global $wpdb;

    // Insert quiz
    $wpdb->insert(
        $wpdb->prefix . 'quizzes',
        array(
            'name' => $_POST['ifeelquizzy_quiz_name']
        )
    );
    $quiz_id = $wpdb->insert_id;

    $character_ids = [];
    // Insert characters
    foreach ($_POST['character_name'] as $index => $character_name) {
        //$character_image = isset($_POST['character_image'][$index]) ? $_POST['character_image'][$index] : null;
        $wpdb->insert(
            $wpdb->prefix . 'characters',
            array(
                'name' => $character_name,
                'description' => $_POST['character_description'][$index],
                //'image' => $character_image,
                'quiz_id' => $quiz_id
            )
        );
        $character_ids[] = $wpdb->insert_id;
    }

    // Insert questions and answers
    foreach ($_POST['question_text'] as $question_index => $question_text) {
        $wpdb->insert(
            $wpdb->prefix . 'questions',
            array(
                'question_text' => $question_text,
                'quiz_id' => $quiz_id
            )
        );
        $question_id = $wpdb->insert_id;

        foreach ($_POST['answer_text'][$question_index] as $answer_index => $answer_text) {
            $wpdb->insert(
                $wpdb->prefix . 'answers',
                array(
                    'answer' => $answer_text,
                    'question_id' => $question_id
                )
            );
            $answer_id = $wpdb->insert_id;

            // Insert points
            foreach ($_POST['answer_points'][$question_index][$answer_index] as $point_index => $point) {
                $wpdb->insert(
                    $wpdb->prefix . 'points',
                    array(
                        'points' => $point,
                        'character_id' => $character_ids[$point_index],
                        'answer_id' => $answer_id
                    )
                );
            }
        }
    }

    return $quiz_id;
}

// Display form on create page
function ifeelquizzy_create_page()
{
?>
    <h2>Create Quiz</h2>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="ifeelquizzy_create_quiz">
        <input type="text" name="ifeelquizzy_quiz_name" placeholder="Quizz name*" required />
        <h2>Characters</h2>
        <div id="characters" class="characters"></div>
        <button type="button" id="add-character">Add Character</button>
        <h2>Questions</h2>
        <div id="questions" class="questions"></div>
        <button type="button" id="add-question">Add Question</button>
        <div class="options">
            <button type="button" class="cancel" id="cancel">Cancel</button>
            <button type="submit" value="submit">Save</button>
        </div>
    </form>
    <script>
        var characterCount = 0;
        var questionCount = 0;

        document.getElementById('add-character').addEventListener('click', function() {
            var character = document.createElement('div');
            character.className = 'character';
            character.dataset.characterId = characterCount;
            character.innerHTML = '<label>Character: ' + characterCount + '</label><input type="text" name="character_name[]" placeholder="Character Name *" required><textarea name="character_description[]" placeholder="Character Description *" required></textarea><input type="file" name="character_image[]" onchange="previewImage(event, \'character_image_preview_' + characterCount + '\')"><img id="character_image_preview_' + characterCount + '" src="#" alt="Character Image Preview" style="max-width: 200px; max-height: 200px;"><button type="button" class="remove-character">Remove Character</button>';
            document.getElementById('characters').appendChild(character);

            var removeButtons = document.getElementsByClassName('remove-character');
            for (var i = 0; i < removeButtons.length; i++) {
                removeButtons[i].addEventListener('click', function(e) {
                    var characterId = e.target.parentNode.dataset.characterId;
                    var pointsFields = document.querySelectorAll('input[data-character-id="' + characterId + '"]');
                    for (var j = 0; j < pointsFields.length; j++) {
                        var pointsFieldId = pointsFields[j].id;
                        var label = document.querySelector('label[for="' + pointsFieldId + '"]');
                        if (label) {
                            label.remove();
                        }
                        pointsFields[j].remove();
                    }
                    e.target.parentNode.remove();
                });
            }

            var answers = document.getElementsByClassName('answer');
            for (var i = 0; i < answers.length; i++) {
                var question = answers[i].parentNode.parentNode;
                var points = document.createElement('input');
                points.type = 'number';
                points.name = 'answer_points[' + question.dataset.questionId + '][' + answers[i].dataset.answerId + '][]';
                points.value = 0;
                points.dataset.characterId = i;
                points.min = 0;
                points.max = 10;
                points.required = true;

                var label = document.createElement('label'); // Create a label element
                label.for = points.id; // Set the for attribute to the id of the points field
                var characterNameInput = document.querySelector('input[name="character_name[]"][data-character-id="' + i + '"]');
                console.log(characterNameInput);
                var characterName = characterNameInput ? characterNameInput.value : 'Character ' + characterCount + ' *';
                label.textContent = 'Points for ' + characterName;

                let pointsDiv = answers[i].querySelector('.points');
                pointsDiv.appendChild(label);
                pointsDiv.appendChild(points);
            }

            characterCount++;
        });
        document.getElementById('add-question').addEventListener('click', function() {
            var question = document.createElement('div');
            question.className = 'question';
            question.innerHTML = '<input type="text" name="question_text[]" placeholder="Question Text *" required><div class="answers"></div><button type="button" class="add-answer">Add Answer</button><button type="button" class="remove-question">Remove Question</button>';
            question.dataset.questionId = questionCount;
            document.getElementById('questions').appendChild(question);

            var removeButtons = question.getElementsByClassName('remove-question');
            for (var i = 0; i < removeButtons.length; i++) {
                removeButtons[i].addEventListener('click', function(e) {
                    e.target.parentNode.remove();
                });
            }

            var addAnswerButton = question.getElementsByClassName('add-answer')[0];
            addAnswerButton.addEventListener('click', function(e) {
                var answer = document.createElement('div');
                answer.className = 'answer';
                answer.innerHTML = '<input type="text" name="answer_text[' + question.dataset.questionId + '][]" placeholder="Answer Text *" required><div class="points"></div><button type="button" class="remove-answer">Remove Answer</button>';
                lastestAnswer = e.target.previousElementSibling.lastElementChild;
                answer.dataset.answerId = lastestAnswer ? parseInt(lastestAnswer.dataset.answerId) + 1 : 0;
                e.target.previousElementSibling.appendChild(answer);

                var removeButtons = answer.getElementsByClassName('remove-answer');
                for (var j = 0; j < removeButtons.length; j++) {
                    removeButtons[j].addEventListener('click', function(e) {
                        e.target.parentNode.remove();
                    });
                }

                var characters = document.getElementsByClassName('character');
                var pointsDiv = answer.querySelector('.points');
                for (var j = 0; j < characters.length; j++) {
                    var points = document.createElement('input');
                    points.type = 'number';
                    points.name = 'answer_points[' + question.dataset.questionId + '][' + answer.dataset.answerId + '][]';
                    points.value = 0;
                    points.dataset.characterId = j;
                    points.dataset.questionId = questionCount;
                    points.id = "points_" + questionCount + "_" + j;
                    points.min = 0;
                    points.max = 10;
                    points.required = true;

                    var label = document.createElement('label'); // Create a label element
                    label.setAttribute('for', "points_" + questionCount + "_" + j);
                    var characterNameInput = document.querySelector('input[name="character_name[]"][data-character-id="' + j + '"]');
                    var characterName = characterNameInput ? characterNameInput.value : 'Character ' + j;
                    label.textContent = 'Points for ' + characterName;

                    pointsDiv.appendChild(label);
                    pointsDiv.appendChild(points);
                }
            });
            questionCount++;
        });


        function previewImage(event, id) {
            var reader = new FileReader();
            reader.onload = function() {
                var output = document.getElementById(id);
                output.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        document.getElementById('cancel').addEventListener('click', function() {
            window.location.href = '/admin.php?page=ifeelquizzy';
        });
    </script>
<?php
}

function updateQuizz()
{
    global $wpdb;
    $quiz_id = $_POST['quiz_id'];

    // Fetch quiz, characters, questions, and answers from database
    $characters = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}characters WHERE quiz_id = %d", $quiz_id));
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}questions WHERE quiz_id = %d", $quiz_id));

    // Fetch answers and points for each question
    foreach ($questions as $question) {
        $question->answers = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}answers WHERE question_id = %d", $question->id));
        foreach ($question->answers as $answer) {
            $answer->points = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}points WHERE answer_id = %d", $answer->id));
        }
    }

    // Update quiz
    $wpdb->update(
        $wpdb->prefix . 'quizzes',
        array('name' => $_POST['ifeelquizzy_quiz_name']),
        array('id' => $quiz_id)
    );

    // Insert characters
    foreach ($_POST['character_name'] as $index => $character_name) {
        $character_image = isset($_POST['character_image'][$index]) ? $_POST['character_image'][$index] : null;
        if (isset($_POST['character_db_id'][$index])) {
            $wpdb->update(
                $wpdb->prefix . 'characters',
                array(
                    'name' => $character_name,
                    'description' => $_POST['character_description'][$index],
                    'image' => $character_image,
                ),
                array(
                    'id' => $_POST['character_db_id'][$index]
                )
            );
        } else {
            //$character_image = isset($_POST['character_image'][$index]) ? $_POST['character_image'][$index] : null;
            $wpdb->insert(
                $wpdb->prefix . 'characters',
                array(
                    'name' => $character_name,
                    'description' => $_POST['character_description'][$index],
                    //'image' => $character_image,
                    'quiz_id' => $quiz_id
                )
            );
            $character_ids[] = $wpdb->insert_id;
        }
    }
    foreach ($characters as $character) {
        if (!in_array($character->id, $_POST['character_db_id'])) {
            $wpdb->delete("{$wpdb->prefix}characters", array('id' => $character->id));
            $wpdb->delete("{$wpdb->prefix}points", array('character_id' => $character->id));
        }
    }

    $question_ids = [];
    // Insert questions
    foreach ($_POST['question_text'] as $index => $question_text) {
        if (isset($_POST['question_db_id'][$index])) {
            $wpdb->update(
                $wpdb->prefix . 'questions',
                array(
                    'question_text' => $question_text,
                ),
                array(
                    'id' => $_POST['question_db_id'][$index]
                )
            );
        } else {
            $wpdb->insert(
                $wpdb->prefix . 'questions',
                array(
                    'question_text' => $question_text,
                    'quiz_id' => $quiz_id
                )
            );
            $question_ids[] = $wpdb->insert_id;
        }
    }
    foreach ($questions as $question) {
        if (!in_array($question->id, $_POST['question_db_id'])) {
            $wpdb->delete("{$wpdb->prefix}questions", array('id' => $question->id));
        }
    }

    return $quiz_id;
}

function ifeelquizzy_modify_page()
{
    global $wpdb;

    // Get quiz ID from URL
    $quiz_id = isset($_GET['id']) ? intval($_GET['id']) : null;

    // Fetch quiz, characters, questions, and answers from database
    $quiz = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$wpdb->prefix}quizzes WHERE id = %d", $quiz_id));
    $characters = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}characters WHERE quiz_id = %d", $quiz_id));
    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}questions WHERE quiz_id = %d", $quiz_id));

    // Fetch answers and points for each question
    foreach ($questions as $question) {
        $question->answers = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}answers WHERE question_id = %d", $question->id));
        foreach ($question->answers as $answer) {
            $answer->points = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}points WHERE answer_id = %d", $answer->id));
        }
    }
?>
    <h2>Modify Quiz</h2>
    <form method="post" action="<?php echo admin_url('admin-post.php'); ?>" enctype="multipart/form-data">
        <input type="hidden" name="action" value="ifeelquizzy_update_quiz">
        <input type="hidden" name="quiz_id" value="<?= $quiz_id ?>">

        <input type="text" name="ifeelquizzy_quiz_name" value="<?php echo esc_attr($quiz->name); ?>" required />

        <h2>Characters</h2>
        <div id="characters" class="characters">
            <?php
            $characterCount = 0;
            foreach ($characters as $character) : ?>
                <div class="character">
                    <input type="hidden" name="character_db_id[]" value="<?= $character->id ?>">
                    <label>Character: <?php $characterCount ?></label>
                    <input type="text" name="character_name[]" placeholder="Character Name *" value="<?php echo esc_attr($character->name); ?>" required />
                    <textarea name="character_description[]" placeholder="Character Description *" required><?php echo esc_attr($character->description); ?></textarea>
                    <input type="file" name="character_image[]" onchange="previewImage(event, 'character_image_preview_<?php echo esc_attr($character->id); ?>')">
                    <img id="character_image_preview_<?php echo esc_attr($character->id); ?>" src="<?php echo esc_attr($character->image_url); ?>" alt="Character Image Preview" style="max-width: 200px; max-height: 200px;">
                    <button type="button" class="remove-character">Remove Character</button>
                </div>
            <?php
                $characterCount += 1;
            endforeach; ?>
        </div>
        <button type="button" id="add-character">Add Character</button>

        <h2>Questions</h2>
        <div id="questions" class="questions">
            <?php foreach ($questions as $question) : ?>
                <div class="question">
                    <input type="hidden" name="question_db_id[]" value="<?= $question->id ?>">
                    <input type="text" name="question_text[]" placeholder="Question Text *" value="<?php echo esc_attr($question->question_text); ?>" required />
                    <div class="answers">
                        <?php foreach ($question->answers as $answer) : ?>
                            <div class="answer">
                                <input type="hidden" name="answer_db_id[]" value="<?= $answer->id ?>">
                                <input type="text" name="answer_text[]" placeholder="Answer Text *" value="<?php echo esc_attr($answer->answer); ?>" required />
                                <div class="points">
                                    <?php foreach ($answer->points as $point) : ?>
                                        <input type="number" name="answer_points[]" value="<?php echo esc_attr($point->points); ?>" required />
                                    <?php endforeach; ?>
                                </div>
                                <button type="button" class="remove-answer">Remove Answer</button>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <button type="button" class="add-answer">Add Answer</button>
                    <button type="button" class="remove-question">Remove Question</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-question">Add Question</button>
        <div class="options">
            <button type="button" class="cancel" id="cancel">Cancel</button>
            <button type="submit">Save</button>
        </div>
    </form>
    <script>
        var characterCount = <?= count($characters) + 1; ?>;
        var questionCount = <?= count($questions) + 1; ?>;


        function removeCharacter() {
            var removeButtons = document.getElementsByClassName('remove-character');
            for (var i = 0; i < removeButtons.length; i++) {
                removeButtons[i].addEventListener('click', function(e) {
                    console.log('remove character clicked');
                    var characterId = e.target.parentNode.dataset.characterId;
                    var pointsFields = document.querySelectorAll('input[data-character-id="' + characterId + '"]');
                    for (var j = 0; j < pointsFields.length; j++) {
                        var pointsFieldId = pointsFields[j].id;
                        var label = document.querySelector('label[for="' + pointsFieldId + '"]');
                        if (label) {
                            label.remove();
                        }
                        pointsFields[j].remove();
                    }
                    e.target.parentNode.remove();
                });
            }
        }

        function addPointsToAnswers() {
            var answers = document.getElementsByClassName('answer');
            for (var i = 0; i < answers.length; i++) {
                var points = document.createElement('input');
                points.type = 'number';
                points.name = 'answer_points[]';
                points.value = 0;
                points.dataset.characterId = i;
                points.min = 0;
                points.max = 10;
                points.required = true;

                var label = document.createElement('label'); // Create a label element
                label.for = points.id; // Set the for attribute to the id of the points field
                var characterNameInput = document.querySelector('input[name="character_name[]"][data-character-id="' + i + '"]');
                console.log(characterNameInput);
                var characterName = characterNameInput ? characterNameInput.value : 'Character ' + characterCount + ' *';
                label.textContent = 'Points for ' + characterName;

                let pointsDiv = answers[i].querySelector('.points');
                pointsDiv.appendChild(label);
                pointsDiv.appendChild(points);
            }
        }

        function addRemoveQuestionListeners() {
            var removeButtons = document.getElementsByClassName('remove-question');
            for (var i = 0; i < removeButtons.length; i++) {
                removeButtons[i].addEventListener('click', function(e) {
                    e.target.parentNode.remove();
                });
            }
        }

        function addRemoveAnswerListeners() {
            var removeButtons = document.getElementsByClassName('remove-answer');
            for (var i = 0; i < removeButtons.length; i++) {
                removeButtons[i].addEventListener('click', function(e) {
                    e.target.parentNode.remove();
                });
            }
        }

        function addAnswerListeners() {
            var addAnswerButton = document.getElementsByClassName('add-answer');
            for (var i = 0; i < addAnswerButton.length; i++) {
                addAnswerButton[i].addEventListener('click', function(e) {
                    var answer = document.createElement('div');
                    answer.className = 'answer';
                    answer.innerHTML = '<input type="hidden" name="answer_db_id[]" value=""><input type="text" name="answer_text[]" placeholder="Answer Text *" required><div class="points"></div><button type="button" class="remove-answer">Remove Answer</button>';
                    e.target.previousElementSibling.appendChild(answer);

                    var characters = document.getElementsByClassName('character');
                    var pointsDiv = answer.querySelector('.points');
                    for (var j = 0; j < characters.length; j++) {
                        var points = document.createElement('input');
                        points.type = 'number';
                        points.name = 'answer_points[]';
                        points.value = 0;
                        points.dataset.characterId = j;
                        points.dataset.questionId = questionCount;
                        points.id = "points_" + questionCount + "_" + j;
                        points.min = 0;
                        points.max = 10;
                        points.required = true;

                        var label = document.createElement('label'); // Create a label element
                        label.setAttribute('for', "points_" + questionCount + "_" + j);
                        var characterNameInput = document.querySelector('input[name="character_name[]"][data-character-id="' + j + '"]');
                        var characterName = characterNameInput ? characterNameInput.value : 'Character ' + j;
                        label.textContent = 'Points for ' + characterName;

                        pointsDiv.appendChild(label);
                        pointsDiv.appendChild(points);
                    }
                });
            }
        }

        removeCharacter();
        addRemoveQuestionListeners();
        addAnswerListeners();
        addRemoveAnswerListeners();

        document.getElementById('add-character').addEventListener('click', function() {
            var character = document.createElement('div');
            character.className = 'character';
            character.dataset.characterId = characterCount;
            character.innerHTML = '<label>Character: ' + characterCount + '</label><input type="text" name="character_name[]" placeholder="Character Name *" required><textarea name="character_description[]" placeholder="Character Description *" required></textarea><input type="file" name="character_image[]" onchange="previewImage(event, \'character_image_preview_' + characterCount + '\')"><img id="character_image_preview_' + characterCount + '" src="#" alt="Character Image Preview" style="max-width: 200px; max-height: 200px;"><button type="button" class="remove-character">Remove Character</button>';
            document.getElementById('characters').appendChild(character);

            removeCharacter();
            addPointsToAnswers();
            characterCount++;
        });

        document.getElementById('add-question').addEventListener('click', function() {
            var question = document.createElement('div');
            question.className = 'question';
            question.innerHTML = '<input type="text" name="question_text[]" placeholder="Question Text *" required><div class="answers"></div><button type="button" class="add-answer">Add Answer</button><button type="button" class="remove-question">Remove Question</button>';
            question.dataset.questionId = questionCount;
            document.getElementById('questions').appendChild(question);

            addRemoveQuestionListeners();
            addAnswerListeners();
            addRemoveAnswerListeners();

            questionCount++;
        });

        function previewImage(event, id) {
            var reader = new FileReader();
            reader.onload = function() {
                var output = document.getElementById(id);
                output.src = reader.result;
            }
            reader.readAsDataURL(event.target.files[0]);
        }

        document.getElementById('cancel').addEventListener('click', function() {
            window.location.href = '/admin.php?page=ifeelquizzy';
        });
    </script>
<?php
}

function deleteQuizz()
{
    global $wpdb;

    // Get quiz ID from URL
    $quiz_id = $_POST['quiz_id'];

    $questions = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}questions WHERE quiz_id = %d", $quiz_id));

    // Fetch answers and points for each question
    foreach ($questions as $question) {
        $question->answers = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}answers WHERE question_id = %d", $question->id));
        foreach ($question->answers as $answer) {
            $answer->points = $wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}points WHERE answer_id = %d", $answer->id));
        }
    }

    // Delete points, answers, questions, characters, and quiz from database
    foreach ($questions as $question) {
        foreach ($question->answers as $answer) {
            $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}points WHERE answer_id = %d", $answer->id));
        }
        $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}answers WHERE question_id = %d", $question->id));
    }
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}questions WHERE quiz_id = %d", $quiz_id));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}characters WHERE quiz_id = %d", $quiz_id));
    $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->prefix}quizzes WHERE id = %d", $quiz_id));

    // Redirect back to the page the user came from
    wp_redirect(wp_get_referer());
    exit;
}
