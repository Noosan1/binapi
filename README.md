---

# binapi Grav Plugin

A secure, configurable REST API plugin for [Grav CMS](https://getgrav.org), enabling automated article and image creation via authenticated endpoints. Ideal for workflow automation and integration with tools like n8n.

---

## Table of Contents

- [Features](#features)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Authentication](#authentication)
- [Usage](#usage)
    - [Create Article Endpoint](#create-article-endpoint)
    - [Upload Image Endpoint](#upload-image-endpoint)
- [Integration Example: n8n](#integration-example-n8n)
- [Security Notes](#security-notes)
- [Contributing](#contributing)
- [License](#license)

---

## Features

- Create articles via REST API
- Upload images via REST API
- Dual authentication: Grav session or Bearer token
- Configurable permissions for folder, article, and image creation
- Designed for automation (e.g., n8n, Zapier, custom scripts)

---

## Requirements

- [Grav CMS](https://getgrav.org) v1.7 or higher
- PHP 7.3+
- (Recommended) [Login Plugin](https://github.com/getgrav/grav-plugin-login) for session authentication

---

## Installation

1. **Download or Clone:**

```bash
git clone https://github.com/yourusername/binapi.git
```

2. **Move to Plugins Folder:**
Place the `binapi` directory into your site's `user/plugins/` directory.
3. **Enable the Plugin:**
    - Via Admin Panel: Go to Plugins > binapi > Enable.
    - Or in `user/config/plugins/binapi.yaml`:

```yaml
enabled: true
```


---

## Configuration

All plugin settings are available via the Admin Panel or by editing `user/config/plugins/binapi.yaml` directly:

```yaml
enabled: true
require_auth: true
api_token: 'your_secure_token_here'
default_folder: '02.blog'
allow_folder_creation: true
allow_article_creation: true
allow_image_upload: true
```

**Key Options:**

- `require_auth`: Enforces authentication for all API requests.
- `api_token`: Bearer token for API authentication.
- `default_folder`: Default folder for new articles/images (e.g., `02.blog`).
- `allow_folder_creation`: Allow API to create new folders if they don't exist.
- `allow_article_creation`: Allow API to create new articles.
- `allow_image_upload`: Allow API to upload images.

---

## Authentication

binapi supports two authentication methods:

1. **Bearer Token (Recommended for API):**
    - Set `require_auth: true` and provide a strong `api_token`.
    - All API requests must include:

```
Authorization: Bearer your_secure_token_here
```

2. **Grav Session (Browser/Logged-in User):**
    - If authenticated via the Grav Login plugin, requests are allowed.

---

## Usage

### Create Article Endpoint

- **URL:** `/binapi/create-article`
- **Method:** `POST`
- **Headers:**
`Authorization: Bearer your_secure_token_here` (if `require_auth` is enabled)
- **Body (JSON):**

```json
{
  "folder": "02.blog",
  "filename": "item.md",
  "content": "---\ntitle: \"My Title\"\n---\nContent goes here.",
  "title": "My Title"
}
```

- **Response:**

```json
{ "success": true, "message": "Article created" }
```


### Upload Image Endpoint

- **URL:** `/binapi/upload-image`
- **Method:** `POST`
- **Headers:**
`Authorization: Bearer your_secure_token_here` (if `require_auth` is enabled)
- **Body (JSON):**

```json
{
  "folder": "02.blog",
  "filename": "image.png",
  "image": "data:image/png;base64,iVBORw0KGgoAAAANSUhEUgA..."
}
```

- **Response:**

```json
{ "success": true, "url": "/user/pages/02.blog/image.png" }
```


---

## Integration Example: n8n

1. **Create Credential:**
    - Type: HTTP Request (Header Auth)
    - Header Name: `Authorization`
    - Value: `Bearer your_secure_token_here`
2. **HTTP Request Node Example:**
    - URL: `https://your-grav-site.com/binapi/create-article`
    - Method: `POST`
    - Authentication: Select your credential
    - Body Content Type: JSON

---

## Security Notes

- **Always use HTTPS** to protect your token and data in transit.
- **Use a strong, unique API token** (e.g., `openssl rand -base64 32`).
- **Restrict permissions** as needed using the plugin's config.
- **Monitor and rotate tokens** periodically for best security practices.

---

## Contributing

Contributions, issues, and feature requests are welcome!
Please open an issue or pull request on GitHub.

---

## License

This project is licensed under the MIT License. See [LICENSE](LICENSE) for details.

---

_Last updated: May 29, 2025_
