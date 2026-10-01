# vikunja-to-md
Spit out the contents of a Vikunja kanban project into a markdown file. Assumes one kanban view per project.

To run:
1. `cp .env.example .env` - fill in your values
2. `php bin/export.php`
3. Files will be written to a subdirectory of exports/. Subdirectory name based on project name.

Attachments, if there are any, will be in their own directory. This directory will be created whether there are attachments or not.


