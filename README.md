# Coding Task - Data Feed

## Description

Data Feed is a command-line program for processing XML files, extracting data and storing it in a relational database.

## Installation

1. Clone the repository:

   ```bash
   git clone https://github.com/chhamza/data-feed-coding-task.git
   ```

2. Navigate to the project directory:

   ```bash
   cd data-feed-coding-task
   ```

3. Install dependencies:

   ```bash
   composer install
   ```

4. Set up the database configuration:

   - Create a db of your choice (In this project MySQL is used)
   - Open `index.php` in your preferred text editor.
   - Update the database host, database name, username, and password.

5. Run the application:

   ```bash
   php index.php
   ```

## Specify XML Files

You can also specify XML files in `index.php` by giving their path. Current XML files are stored in xml_files.

## Testing

Run tests using PHPUnit. Tests are inside `tests` folder. You can run tests with following command:

```bash
vendor/bin/phpunit DatabaseConfigTest.php
```

```bash
vendor/bin/phpunit ProgramTest.php
```

```bash
vendor/bin/phpunit MySQLStorageTest.php
```
