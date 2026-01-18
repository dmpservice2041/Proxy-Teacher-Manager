# Contributing to Proxy Teacher Management System

First off, thank you for considering contributing to this project! It's people like you that make it a great tool for everyone.

## Table of Contents

1. [How Can I Contribute?](#how-can-i-contribute)
   - [Reporting Bugs](#reporting-bugs)
   - [Suggesting Enhancements](#suggesting-enhancements)
   - [Pull Requests](#pull-requests)
2. [Development Setup](#development-setup)
3. [Style Guidelines](#style-guidelines)

## How Can I Contribute?

### Reporting Bugs

If you find a bug, please open an **Issue** on GitHub. Be sure to include:
- A clear, descriptive title.
- Steps to reproduce the bug.
- Actual vs. Expected behavior.
- Screenshots if applicable.

### Suggesting Enhancements

We welcome new ideas! If you have a suggestion:
- Open an **Issue** with the tag `enhancement`.
- Describe the feature and why it would be useful.
- If possible, suggest how it might be implemented.

### Pull Requests

1. **Fork** the repository.
2. Create a new branch (`git checkout -b feature/AmazingFeature`).
3. Make your changes.
4. **Commit** your changes (`git commit -m 'Add some AmazingFeature'`).
5. **Push** to the branch (`git push origin feature/AmazingFeature`).
6. Open a **Pull Request**.

## Development Setup

Please refer to the [README.md](README.md) for full installation and setup instructions.

1. Ensure you have PHP 7.4+ and MySQL/MariaDB installed.
2. Import the database schema from `sql/schema.sql`.
3. Use `sql/seed_data.sql` for the initial admin user.

## Style Guidelines

- **PHP:** Follow PSR-12 coding standards.
- **Naming:** Use meaningful variable and function names.
- **Comments:** Keep comments essential. Avoid verbose explanations of obvious code.
- **Organization:** Keep logic in `models/` or `services/` and UI in the root PHP files.

## Questions?

If you have questions, feel free to reach out to the project maintainers through GitHub Issues.

Happy coding! 🚀
