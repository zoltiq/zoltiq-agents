
**Zoltiq Agents is an AI agent platform for WordPress.**
Create AI agents that can understand requests, use WordPress tools,
access website knowledge and perform tasks.

[![WordPress](https://img.shields.io/badge/WordPress-Plugin-21759B.svg)](https://wordpress.org/)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](LICENSE)



# Zoltiq Agents

**AI agents for WordPress.**

Zoltiq Agents is a WordPress plugin for creating and running AI agents that can understand natural-language requests, use tools, access your website knowledge, and perform tasks within WordPress.

Unlike a traditional chatbot that only generates text, an AI agent can **use tools and take actions**.

> Build AI agents that work with your WordPress site — not just chat with your visitors.

[Website](https://zoltiq.com/) · [Documentation](https://zoltiq.com/) · [Issues](../../issues)

---

## ✨ Features

### 🤖 AI Agents

Create configurable AI agents for different tasks and use cases.

Agents can be configured with:

* Custom system instructions
* AI model and provider settings
* Tools and capabilities
* Website knowledge
* Conversation settings
* Custom workflows

### 💬 Conversational Chat

Add an AI-powered chat interface to your WordPress website.

Visitors can communicate with your agent using natural language and receive responses based on the agent's configuration and available tools.

### 🛠️ WordPress Tools & Abilities

Zoltiq Agents can work with the **WordPress Abilities API** and other available tools.

This allows agents to discover and use capabilities provided by WordPress and installed plugins.

Depending on the enabled capabilities, an agent can perform tasks such as:

* Inspecting WordPress configuration
* Working with plugins and themes
* Reading website content
* Searching available data
* Performing administrative tasks
* Working with WooCommerce data
* Executing custom tools

The available capabilities depend on your WordPress installation and configuration.

### 🧠 Website Knowledge

Agents can use your website content as a knowledge source.

You can embed selected WordPress content and make it available to your AI agents for more relevant answers.

Supported content can include:

* Posts
* Pages
* WooCommerce products
* Product variations

### 🔌 Multiple AI Providers

Zoltiq Agents is designed to work with multiple AI providers.

Depending on your configuration, you can connect your WordPress site to supported providers and select the model used by your agents.

The plugin follows a **Bring Your Own API Key (BYOK)** approach — you provide the API credentials for the AI provider you use.

### ⚙️ Workflows

Agents can combine multiple steps and tools to complete more complex tasks.

This makes it possible to build agents for use cases such as:

* Customer support
* WordPress administration
* Product discovery
* Knowledge-base assistance
* Task automation
* Website assistance

---

## 🧩 What can you build?

Zoltiq Agents can be used to create different types of AI assistants.

### Customer Support Agent

Answer questions about your website, services, products, or documentation.

### WordPress Administrator Agent

Help administrators work with WordPress using available tools and capabilities.

### Knowledge Agent

Search and use your website content to answer questions based on your own data.

### Product Assistant

Help visitors find and understand products using WooCommerce data.

### Task Agent

Execute multi-step tasks using WordPress tools and custom capabilities.

---

## 🚀 Installation

### Option 1 — Download from GitHub

1. Download the latest release or clone this repository.
2. Upload the plugin to:

```text
/wp-content/plugins/zoltiq-agents/
```

3. Activate **Zoltiq Agents** from:

```text
WordPress → Plugins
```

4. Open the **Zoltiq Agents** settings in the WordPress administration panel.
5. Configure your AI provider.
6. Create your first agent.

### Option 2 — Clone the repository

```bash
cd wp-content/plugins
git clone https://github.com/zoltiq/zoltiq-agents.git zoltiq-agents
```

Then activate the plugin from the WordPress administration panel.

---

## ⚡ Quick Start

After activating the plugin:

1. Configure an AI provider.
2. Create a new agent.
3. Define the agent's instructions.
4. Select the tools and capabilities available to the agent.
5. Configure website knowledge if required.
6. Add the agent to your website.
7. Start interacting with it through the chat interface.

---

## 🏗️ How It Works

A Zoltiq agent follows an execution loop:

```text
User request
     ↓
Agent understands the request
     ↓
Selects required tools
     ↓
Executes tools / WordPress Abilities
     ↓
Processes the results
     ↓
Returns the result to the user
```

This allows the agent to go beyond generating text.

For example:

```text
User:
"Find the plugins installed on my website."

        ↓

Agent:
Understands the request

        ↓

Tool:
Retrieves installed plugins

        ↓

Agent:
Processes the result

        ↓

User:
Receives the list of plugins
```

---

## 🔐 Security

AI agents can potentially execute actions on your WordPress site.

For this reason, **only enable tools that you trust the agent to use**.

API keys should be configured on the WordPress server and should not be exposed to the browser.

The permissions and capabilities available to an agent should be considered when deploying an agent in a production environment.

---

## 🧑‍💻 Developers

Zoltiq Agents is designed to be extensible.

Developers can build agents around the existing WordPress environment by combining:

* WordPress Abilities
* Custom tools
* External services
* Website knowledge
* Multi-step workflows
* Different AI providers

The agent architecture uses concepts from **LangChain** and **LangGraph** for tool execution and workflow orchestration.

---

## 📦 Free Version

This repository contains the **free version of Zoltiq Agents**.

The free version provides the core functionality required to create and run AI agents on WordPress.

Additional functionality and advanced features are available in the commercial version.

👉 [Visit Zoltiq Agents](https://zoltiq.com/)

---

## 📋 Requirements

Zoltiq Agents requires:

* WordPress
* PHP version compatible with your WordPress installation
* An API key for a supported AI provider, unless using a local/self-hosted provider
* HTTPS is recommended for production environments

> Specific requirements may change between plugin versions.

---

## 🌐 Links

* **Website:** https://zoltiq.com/
* **Documentation:** https://zoltiq.com/
* **Issues:** [GitHub Issues](../../issues)

---

## 🤝 Contributing

If you find a bug or have an idea for improving Zoltiq Agents, please open an issue in this repository.

For larger changes, please open an issue first to discuss the proposed implementation.

---

## 📄 License

See the [`LICENSE`](LICENSE) file for license information.

---

## About Zoltiq Agents

Zoltiq Agents brings AI agents directly into WordPress.

Instead of building a separate AI backend, you can configure agents that work with the WordPress environment, its tools, content, and available capabilities.

**Give your WordPress site an AI agent — not just a chat window.**
