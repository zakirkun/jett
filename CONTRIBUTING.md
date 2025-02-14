# Contributing to Jett ORM

We love your input! We want to make contributing to Jett ORM as easy and transparent as possible, whether it's:

- Reporting a bug
- Discussing the current state of the code
- Submitting a fix
- Proposing new features
- Becoming a maintainer

## We Develop with Github
We use Github to host code, to track issues and feature requests, as well as accept pull requests.

## We Use [Github Flow](https://guides.github.com/introduction/flow/index.html)
Pull requests are the best way to propose changes to the codebase. We actively welcome your pull requests:

1. Fork the repo and create your branch from `main`.
2. If you've added code that should be tested, add tests.
3. If you've changed APIs, update the documentation.
4. Ensure the test suite passes.
5. Make sure your code lints.
6. Issue that pull request!

## Any contributions you make will be under the MIT Software License
In short, when you submit code changes, your submissions are understood to be under the same [MIT License](http://choosealicense.com/licenses/mit/) that covers the project. Feel free to contact the maintainers if that's a concern.

## Report bugs using Github's [issue tracker](https://github.com/zakirkun/jett/issues)
We use GitHub issues to track public bugs. Report a bug by [opening a new issue](https://github.com/zakirkun/jett/issues/new); it's that easy!

## Write bug reports with detail, background, and sample code

**Great Bug Reports** tend to have:

- A quick summary and/or background
- Steps to reproduce
  - Be specific!
  - Give sample code if you can.
- What you expected would happen
- What actually happens
- Notes (possibly including why you think this might be happening, or stuff you tried that didn't work)

## Development Process

1. **Setting up the development environment**
   ```bash
   # Clone the repository
   git clone https://github.com/yourusername/jett.git
   cd jett

   # Install dependencies
   composer install

   # Run tests
   composer test
   ```

2. **Coding Standards**
   - Follow PSR-12 coding standards
   - Use type hints where possible
   - Write docblocks for all methods
   - Keep methods small and focused
   - Write unit tests for new features

3. **Testing**
   - Write unit tests using PHPUnit
   - Ensure all tests pass before submitting PR
   - Add integration tests for complex features
   - Maintain test coverage above 80%

4. **Documentation**
   - Update README.md if needed
   - Add PHPDoc blocks to all new code
   - Update API documentation
   - Include examples for new features

## Branch Naming Convention

- Feature: `feature/your-feature-name`
- Bugfix: `bugfix/issue-description`
- Hotfix: `hotfix/issue-description`
- Release: `release/version-number`

## Commit Message Format

We follow the [Conventional Commits](https://www.conventionalcommits.org/) specification:

```
<type>(<scope>): <description>

[optional body]

[optional footer]
```

Types:
- feat: A new feature
- fix: A bug fix
- docs: Documentation only changes
- style: Changes that do not affect the meaning of the code
- refactor: A code change that neither fixes a bug nor adds a feature
- perf: A code change that improves performance
- test: Adding missing tests or correcting existing tests
- chore: Changes to the build process or auxiliary tools

Example:
```
feat(query-builder): add support for nested transactions

- Added TransactionManager class
- Implemented savepoint functionality
- Added deadlock detection and retry mechanism

Closes #123
```

## License
By contributing, you agree that your contributions will be licensed under its MIT License.
