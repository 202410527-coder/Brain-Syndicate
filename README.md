# FINAL PROJECT SUBMISSION
### *Brain Syndicate -- Two-Player Web-Based Game Using OOP in PHP*

## Group Information

**Group Name:** Brain Syndicate\
**Members & Contributions:**\
- **AQUINO, Joseph Angelo** -- CSS Coding\
- **MERCADO, JOHN CALVIN** -- PHP Coding\
- **SANDING, NEON** -- PHP, HTML, CSS\
- **ZACARIAS, EMMANUEL EZEKIEL** -- HTML Coding

## Project Overview

### Game Title: Brain Syndicate

Brain Syndicate is a word-guessing game inspired by Wordle, featuring
three difficulty modes---Easy, Normal, and Hard. Each mode provides a
different number of allowed attempts.

**Objective:**\
Challenge the players' critical thinking by guessing the hidden word
within a limited number of attempts.

**Win/Loss Conditions:**\
- **Win:** Correctly guess the word → earn **1 point**\
- **Loss:** Fail to guess within allowed attempts → **0 points**

## Technology Stack

-   PHP\
-   HTML & CSS\
-   JSON (Leaderboard system)\
-   Local JSON database (`scores.json`)

## Game Modes

  Mode     Attempts
  -------- ----------
  Easy     8
  Normal   6
  Hard     5

## How to Play

1.  Select a difficulty mode.\
2.  Guess the hidden word using the on-screen keyboard.\
3.  Check feedback after each attempt.\
4.  Score is saved into `scores.json`.

## How to Run Locally

1.  Install XAMPP.\
2.  Place project folder in `htdocs/`.\
3.  Ensure `index.php` and `scores.json` exist.\
4.  Run via `http://localhost/brain-syndicate/`.

## OOP Implementation

### Encapsulation

Game state and scoring logic stored inside functions/private variables.

### Inheritance

Modes can inherit from a base Game class.

### Polymorphism

Shared method like `makeGuess()` works with any mode class.

### Abstraction

User sees only UI; internal logic is hidden.

## Repository Requirements

-   README.md\
-   All source code\
-   JSON database\
-   Technical documentation

## Video Demonstration Requirements

-   Minimum 5 minutes\
-   Show gameplay + face + narration\
-   Explain features, contributions, and challenges\
-   Upload link (YouTube or Google Drive)

## Submission Process

1.  Prepare GitHub repository.\
2.  Submit link via GCLamp.
