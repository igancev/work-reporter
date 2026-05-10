# ⏱️ Work Reporter

> CLI tool for automatically reporting work time from local sources (time trackers) to task trackers.

[![Build](https://github.com/igancev/work-reporter/actions/workflows/quality-checks.yaml/badge.svg)](https://github.com/igancev/work-reporter/actions)
[![License: MIT](https://img.shields.io/badge/License-MIT-green.svg)](https://opensource.org/licenses/MIT)

---

### 😩 The Problem

At work, you need to log time spent on tasks. Throughout the day you switch between different tasks and activities, but task trackers like YouTrack often lack convenient built-in time tracking tools. Meanwhile, dedicated time trackers like [SuperProductivity](https://super-productivity.com/) do this job perfectly — but at the end of the day, manually copying worklogs from one tool to another is tedious, repetitive, and boring.

### 🚀 The Solution

**Work Reporter** automates this routine. It reads time entries from your local time tracker and submits them to your task tracker with a single command. Just configure the source-to-destination mapping once, and let Work Reporter handle all the dirty work for you.

### ✨ Features & Supported Integrations

- 📥 **Supported sources:**
  - [SuperProductivity](https://super-productivity.com/) (sync file)
  - Plain JSON file
- 📤 **Supported destinations:**
  - [YouTrack](https://www.jetbrains.com/youtrack/)
- 🔀 Automatic grouping of identical time entries
- 🕐 Filtering out entries shorter than 1 minute
- 🔧 Simple YAML configuration

> 🏗️ The architecture is extensible — adding new sources and destinations is straightforward.

---

### 📦 Installation (Linux)

One-liner install — download the latest binary and you're ready to go:

```bash
curl -sL "$(curl -s https://api.github.com/repos/igancev/work-reporter/releases/latest | grep -oP '"browser_download_url":\s*"\K[^"]*linux-amd64')" -o work-reporter && chmod +x work-reporter && sudo mv work-reporter /usr/local/bin/
```

Verify the installation:

```bash
work-reporter --version
```

> 💡 You can also browse [all releases](https://github.com/igancev/work-reporter/releases) for manual download.

---

### 🔧 Quick Start

Generate a default configuration file:

```bash
work-reporter init
```

This creates a config file at `~/.config/work-reporter/config.yaml` with a template you can edit.

---

### ⚙️ Configuration

Create a config file at `~/.config/work-reporter/config.yaml`:

```yaml
# Active source and destination (choose one of each)
source: superProductivity
destination: youTrack

sources:
  # SuperProductivity sync file path
  superProductivity:
    syncFilePath: ~/.config/superproductivity/__meta_

  # Or use a plain JSON file as a source
  plainJson:
    filePath: ~/worklogs.json

destinations:
  youTrack:
    # Your YouTrack instance URL
    url: https://youtrack.example.com
    # Permanent token for authentication (see below)
    token: perm-your-permanent-token
```

#### 🔑 Getting a YouTrack Token

1. Open YouTrack → **Profile** → **Account Security**
2. Click **New token…**
3. Set the scope and create the token
4. Paste the token into your config file

#### 🔄 Setting up SuperProductivity Sync

1. Open SuperProductivity → **Settings** → **Sync & Export** → **Sync**
2. Enable **Enable Syncing**
3. Choose **Sync folder path**
4. Paste the same path into `syncFilePath` in your config file

---

### 🎯 Usage

```bash
# Submit worklogs for today (default)
work-reporter

# Submit worklogs for a specific date range (optional)
work-reporter --from="2026-05-01" --to="2026-05-02"
```

> 📅 Both `--from` and `--to` are optional. If omitted, today's date is used by default.

---

### 🛠️ Development

#### Requirements

- PHP 8.4+
- Composer

#### Setup

```bash
composer install
```

#### Commands

| Command             | Description               |
|---------------------|---------------------------|
| `make cs`           | Check code style (PSR-12) |
| `make cs-fix`       | Auto-fix code style       |
| `make stat-analyze` | Static analysis (PHPStan) |
| `make unit`         | Run unit tests            |
| `make functional`   | Run functional tests      |
| `make check-all`    | Run all checks            |

---

### 🏗️ Architecture

The project follows a **Source → Destination** pattern:

```
[SuperProductivity / JSON] → TimeEntry[] → [YouTrack]
```

- `Source` — reads time entries from a local data source
- `TimeEntry` — value object representing a single entry
- `Destination` — submits entries to a task tracker

---

### 🤝 Contributing

Contributions are welcome! Please read the [Contribution Guide](CONTRIBUTING.md)
before opening a Pull Request — it describes the testing and CI requirements
your change must satisfy.

Quick start:

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/awesome`)
3. Cover your changes with tests
4. Make sure `make check-all` passes ✅
5. Open a Pull Request

---

### 📄 License

[MIT](https://opensource.org/licenses/MIT) © [igancev](https://github.com/igancev)

---

### ⭐ Like the project?

Give it a star — it helps and motivates! 🌟
