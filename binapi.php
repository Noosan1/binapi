<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;

/**
 * binapi Plugin
 * Provides secure API endpoints for Grav CMS with dual authentication support.
 */
class BinapiPlugin extends Plugin
{
    /**
     * Register plugin events.
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0]
        ];
    }

    /**
     * Initialize plugin on frontend only.
     */
    public function onPluginsInitialized()
    {
        if ($this->isAdmin()) {
            return;
        }

        $this->enable([
            'onPageInitialized' => ['onPageInitialized', 0],
        ]);
    }

    /**
     * Handle API endpoints and authentication.
     */
    public function onPageInitialized()
    {
        // Enforce authentication if enabled in config
        if ($this->config->get('plugins.binapi.require_auth')) {
            $this->authenticateRequest();
        }

        $uri = $this->grav['uri'];
        $route = rtrim($uri->path(), '/');

        // Article creation endpoint
        if ($route === '/binapi/create-article' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleCreateArticle();
        }

        // Image upload endpoint
        if ($route === '/binapi/upload-image' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            // Check if image upload is allowed
            if (!$this->config->get('plugins.binapi.allow_image_upload', true)) {
                $this->sendJson(['error' => 'Image upload is disabled'], 403);
            }
            $this->handleUploadImage();
        }
    }

    /**
     * Authenticate request using Bearer token or Grav session.
     */
    private function authenticateRequest()
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
        $apiToken = $this->config->get('plugins.binapi.api_token');

        // Token-based authentication
        if ($apiToken && strpos($authHeader, 'Bearer ') === 0) {
            $token = substr($authHeader, 7);
            if (hash_equals($apiToken, $token)) {
                return; // Valid token
            }
        }

        // Session-based authentication
        $user = $this->grav['user'];
        if ($user->authenticated) {
            return; // Valid session
        }

        $this->sendJson(['error' => 'Authentication required'], 401);
    }

    /**
     * Handle article creation via API.
     */
    private function handleCreateArticle()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $folder = $data['folder'] ?? $this->config->get('plugins.binapi.default_folder', 'blog');
        $filename = $data['filename'] ?? 'item.md';
        $content = $data['content'] ?? '';
        $title = $data['title'] ?? null;

        // Extract title from frontmatter if not provided
        if (!$title && preg_match('/^---\s*[\r\n]+title:\s*["\']?([^"\']+)["\']?/mi', $content, $matches)) {
            $title = trim($matches[1]);
        }

        if (!$folder || !$title) {
            $this->sendJson(['error' => 'Missing folder or title'], 400);
        }

        $pagesDir = $this->grav['locator']->findResource('user://pages', true);
        $folderPath = $pagesDir . '/' . $folder;

        // Create folder if allowed and doesn't exist
        if (!file_exists($folderPath)) {
            if ($this->config->get('plugins.binapi.allow_folder_creation', true)) {
                if (!mkdir($folderPath, 0755, true)) {
                    $this->sendJson(['error' => 'Failed to create article folder'], 500);
                }
            } else {
                $this->sendJson(['error' => 'Folder does not exist and creation is disabled'], 400);
            }
        }

        $mdFile = $folderPath . '/' . $filename;
        if (file_exists($mdFile)) {
            $this->sendJson(['error' => 'Article file already exists'], 409);
        }

        // Add frontmatter if missing
        if (preg_match('/^---/', $content)) {
            $writeContent = $content;
        } else {
            $frontmatter = "---\ntitle: \"$title\"\n---\n";
            $writeContent = $frontmatter . $content;
        }

        if (!file_put_contents($mdFile, $writeContent)) {
            $this->sendJson(['error' => 'Failed to write article file'], 500);
        }

        $this->sendJson(['success' => true, 'message' => 'Article created']);
    }

    /**
     * Handle image upload via API.
     */
    private function handleUploadImage()
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $folder = $data['folder'] ?? $this->config->get('plugins.binapi.default_folder', 'blog');
        $imageData = $data['image'] ?? null;
        $filename = $data['filename'] ?? null;

        if (!$folder || !$imageData || !$filename) {
            $this->sendJson(['error' => 'Missing required parameters'], 400);
        }

        // Sanitize filename
        $filename = preg_replace('/[^a-z0-9\-_\.]/i', '', $filename);

        // Extract and decode base64 image data
        if (!preg_match('/^data:image\/(\w+);base64,/', $imageData, $matches)) {
            $this->sendJson(['error' => 'Invalid image format'], 400);
        }
        $imageData = base64_decode(substr($imageData, strpos($imageData, ',') + 1));
        if ($imageData === false) {
            $this->sendJson(['error' => 'Base64 decoding failed'], 400);
        }

        // Validate image type and size
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mimeType = $finfo->buffer($imageData);
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        if (!in_array($mimeType, $allowedTypes)) {
            $this->sendJson(['error' => 'Unsupported file type'], 415);
        }
        if (strlen($imageData) > 5 * 1024 * 1024) {
            $this->sendJson(['error' => 'File size exceeds 5MB limit'], 413);
        }

        // Save image in article folder
        $imageDir = $this->grav['locator']->findResource('user://pages', true) . '/02.' . $folder;
        if (!file_exists($imageDir)) {
            if ($this->config->get('plugins.binapi.allow_folder_creation', true)) {
                if (!mkdir($imageDir, 0755, true)) {
                    $this->sendJson(['error' => 'Failed to create article directory'], 500);
                }
            } else {
                $this->sendJson(['error' => 'Folder does not exist and creation is disabled'], 400);
            }
        }
        $filePath = $imageDir . '/' . $filename;
        if (file_exists($filePath)) {
            $this->sendJson(['error' => 'Image file exists'], 409);
        }
        if (!file_put_contents($filePath, $imageData)) {
            $this->sendJson(['error' => 'Failed to save image'], 500);
        }

        $this->sendJson([
            'success' => true,
            'url' => "/user/pages/02.$folder/$filename"
        ]);
    }

    /**
     * Send JSON response and exit.
     */
    private function sendJson($data, $status = 200)
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
        exit();
    }
}
