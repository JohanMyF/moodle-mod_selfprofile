# SelfProfile Activity Module for Moodle

`mod_selfprofile` is a Moodle activity module that allows a teacher to create a reusable self-profile questionnaire made up of categories, statements, and a Likert-type response scale.

The activity is designed for reflective self-assessment rather than formal psychological testing. A teacher creates a set of categories, adds statements under those categories, defines the response scale, and provides instructions to students. Students then work through the statements in a randomised order, save progress as they go, and receive a category-based graphical summary after completion.

## Educational Purpose

The purpose of SelfProfile is to help learners reflect on their interests, preferences, strengths, aptitudes, or self-perceived tendencies in a structured way.

Possible uses include:

* vocational reflection;
* leadership development;
* spiritual-gifts or ministry-fit reflection;
* study-skills self-assessment;
* team-role reflection;
* professional development;
* course-readiness diagnostics;
* personal learning-profile activities.

The plugin is not intended to provide a clinical, psychometric, or high-stakes diagnostic instrument. It is intended as a teacher-authored reflective learning activity.

## Teacher Workflow

The teacher creates a SelfProfile activity inside a Moodle course.

During setup, the teacher defines:

1. Activity name.
2. Introductory description.
3. Student instructions.
4. A Likert-type scale.
5. A set of categories.
6. A set of statements linked to those categories.
7. Optional settings such as whether results are visible immediately after completion.

Example category:

> Administration and Organisation

Example statement:

> I enjoy organising people, projects and events.

Example Likert scale:

1. Strongly disagree
2. Disagree
3. Not sure
4. Agree
5. Strongly agree

The teacher may also import a SelfProfile structure from JSON or export the current activity structure as JSON for sharing with other teachers.

## Student Workflow

When a student opens the activity, the student sees:

* the teacher’s instructions;
* one statement at a time;
* the Likert-type response scale;
* progress information.

Statements are presented in a randomised order drawn from all teacher-created statements. The student’s progress is saved after each response so that the student can leave and return later.

Once all statements have been answered, the student submits the completed profile.

After submission, the student sees a graphical summary showing category scores from highest to lowest.

## Results

Results are calculated per category.

Each category score is based on the student’s responses to statements belonging to that category. The first version will use a simple average score per category unless later changed.

Example result:

| Rank | Category                        | Average Score |
| ---: | ------------------------------- | ------------: |
|    1 | Administration and Organisation |           4.6 |
|    2 | Coaching and Development        |           4.2 |
|    3 | Design and Visualisation        |           3.8 |

The final student view should include a graph, most likely a horizontal bar chart sorted from highest-scoring category to lowest-scoring category.

## JSON Sharing

A teacher should be able to export the structure of a SelfProfile activity as a JSON file.

The JSON file should include:

* metadata;
* student instructions;
* scale options;
* categories;
* statements;
* version information.

The JSON file should not include student responses, student names, grades, or any personal data.

Another teacher should be able to import the JSON file, edit it, adapt it, and use it in a different Moodle course.

## Privacy

The plugin stores student responses and calculated results.

The plugin must therefore implement Moodle’s privacy API.

The plugin should support:

* exporting a user’s responses;
* deleting a user’s responses;
* deleting all user data for a context;
* avoiding unnecessary storage of personal information.

The exported teacher JSON file must not contain student data.

## Moodle Development Principles

The plugin should follow Moodle plugin development standards.

This includes:

* no hardcoded user-facing language strings;
* all user-facing strings must be in language files;
* use of Mustache templates where practical;
* JavaScript should be placed in AMD modules;
* no inline JavaScript mixed into PHP pages;
* no unnecessary inline HTML in PHP;
* use of Moodle renderer/output classes where practical;
* correct capability checks;
* correct use of `require_login()` and course-module context;
* correct use of Moodle forms;
* correct backup and restore support;
* correct privacy provider implementation;
* database tables defined through `install.xml`;
* upgrade steps handled through `upgrade.php`;
* external services used for AJAX actions;
* Moodle boilerplate comments in PHP files.

## Suggested First-Version Scope

The first working version should aim for the following:

### Teacher setup

* Activity name.
* Activity intro.
* Student instructions.
* JSON text area or file upload for the profile structure.
* JSON export option.
* Basic validation of imported JSON.

### Student activity

* Display one random unanswered statement at a time.
* Save each response immediately.
* Allow the student to return later.
* Show progress.
* Allow final submission once all statements are answered.
* Show category results after submission.

### Results

* Calculate average score per category.
* Sort categories from highest to lowest.
* Show a simple graphical summary.

### Later Enhancements

Possible later enhancements include:

* teacher-side visual editor for categories and statements;
* drag-and-drop ordering of categories;
* reverse-scored statements;
* category descriptions;
* student downloadable report;
* teacher dashboard;
* cohort-level anonymised summaries;
* gradebook integration;
* completion conditions;
* multiple attempts;
* time-open and time-close settings;
* richer charting;
* template library of shareable SelfProfile instruments.

## Proposed Database Tables

The plugin is expected to need the following core tables:

### `selfprofile`

Stores the activity instance.

Possible fields:

* `id`
* `course`
* `name`
* `intro`
* `introformat`
* `instructions`
* `instructionsformat`
* `scalejson`
* `configjson`
* `timecreated`
* `timemodified`

### `selfprofile_categories`

Stores teacher-created categories.

Possible fields:

* `id`
* `selfprofileid`
* `name`
* `description`
* `sortorder`
* `timecreated`
* `timemodified`

### `selfprofile_statements`

Stores teacher-created statements.

Possible fields:

* `id`
* `selfprofileid`
* `categoryid`
* `statement`
* `sortorder`
* `enabled`
* `timecreated`
* `timemodified`

### `selfprofile_attempts`

Stores one student’s attempt.

Possible fields:

* `id`
* `selfprofileid`
* `userid`
* `status`
* `statementorderjson`
* `timecreated`
* `timemodified`
* `timesubmitted`

### `selfprofile_responses`

Stores individual student responses.

Possible fields:

* `id`
* `attemptid`
* `statementid`
* `scalevalue`
* `timecreated`
* `timemodified`

## Proposed JSON Structure

Example:

```json
{
  "schema": "mod_selfprofile",
  "schemaVersion": 1,
  "name": "Example SelfProfile",
  "instructions": "Read each statement carefully and select the response that best reflects you.",
  "scale": [
    {"value": 1, "label": "Strongly disagree"},
    {"value": 2, "label": "Disagree"},
    {"value": 3, "label": "Not sure"},
    {"value": 4, "label": "Agree"},
    {"value": 5, "label": "Strongly agree"}
  ],
  "categories": [
    {
      "id": "administration",
      "name": "Administration and Organisation",
      "description": "Planning, organising, coordinating and managing tasks.",
      "statements": [
        "I enjoy organising people, projects and events.",
        "I like creating order where things are unclear or disorganised."
      ]
    },
    {
      "id": "coaching",
      "name": "Coaching and Development",
      "description": "Helping others learn, grow and prepare for tasks.",
      "statements": [
        "I enjoy helping others understand difficult ideas.",
        "I like preparing people to succeed in a task."
      ]
    }
  ]
}
```

## Development Strategy

The plugin should be developed in small working stages.

Suggested stages:

1. Create the plugin skeleton.
2. Add installation database tables.
3. Add the basic Moodle activity setup form.
4. Add JSON import during setup.
5. Add teacher export.
6. Add student attempt creation.
7. Add save-response AJAX service.
8. Add progress and resume support.
9. Add final submission.
10. Add results calculation.
11. Add graph display.
12. Add privacy provider.
13. Add backup and restore.
14. Add polish, validation and compliance review.

The first milestone is a plugin that installs cleanly and appears in Moodle’s activity chooser.
