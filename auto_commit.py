import subprocess
import time
import random
import string
import os

# Function to generate a random comment
def generate_random_comment():
    length = random.randint(5, 15)
    return ''.join(random.choices(string.ascii_letters + string.digits, k=length))

# List of meaningful commit messages
meaningful_messages = [
    "Fix minor bug",
    "Update documentation",
    "Add new feature",
    "Refactor code",
    "Improve performance",
    "Clean up code",
    "Add tests",
    "Update dependencies",
    "Fix typo",
    "Optimize code"
]

# Function to run git commands
def run_git_command(command):
    try:
        result = subprocess.run(command, shell=True, check=True, capture_output=True, text=True, cwd=os.getcwd())
        print(f"Git command: {command}")
        print(f"Output: {result.stdout}")
        if result.stderr:
            print(f"Error: {result.stderr}")
    except subprocess.CalledProcessError as e:
        print(f"Error running command '{command}': {e}")

# Path to the file to modify
file_path = 'commits.txt'

run_git_command('git switch rakib')

while True:
    # Read the file
    if os.path.exists(file_path):
        with open(file_path, 'r') as f:
            lines = f.readlines()
    else:
        lines = []

    # Add a new line with random comment
    new_comment = f"# Random comment: {generate_random_comment()}\n"
    lines.append(new_comment)

    # Remove the first line if there are more than 1 line
    if len(lines) > 1:
        lines.pop(0)

    # Write back to the file
    with open(file_path, 'w') as f:
        f.writelines(lines)

    # Git commands
    run_git_command('git add commits.txt')
    commit_message = random.choice(meaningful_messages)
    run_git_command(f'git commit -m "{commit_message}"')
    run_git_command('git push -u origin rakib')

    # Wait for 1 minute
    time.sleep(60)
