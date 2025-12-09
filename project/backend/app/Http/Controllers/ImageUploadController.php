<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageUploadController extends Controller
{
    /**
     * Handle image upload.
     */
    public function upload(Request $request)
    {
        $request->validate([
            'image' => 'required|image|mimes:jpeg,png,jpg,gif|max:20480',
        ]);

        if (!$request->hasFile('image')) {
            return response()->json(['error' => 'No image provided'], 400);
        }

        $image = $request->file('image');
        $filename = Str::random(20) . '.jpg';

        // Optimize and resize image
        $optimizedImage = $this->optimizeImage($image->getRealPath());

        // Save optimized image
        Storage::disk('public')->put('images/' . $filename, $optimizedImage);
        $path = 'images/' . $filename;

        return response()->json([
            'message' => 'Image uploaded and optimized successfully',
            'path' => $path,
            'url' => '/storage/' . $path,
            'size' => strlen($optimizedImage),
        ], 201);
    }

    /**
     * Optimize image: resize and compress.
     */
    private function optimizeImage($imagePath)
    {
        // Get image info
        $imageInfo = getimagesize($imagePath);
        $mimeType = $imageInfo['mime'];

        // Create image resource based on type
        switch ($mimeType) {
            case 'image/jpeg':
                $sourceImage = imagecreatefromjpeg($imagePath);
                break;
            case 'image/png':
                $sourceImage = imagecreatefrompng($imagePath);
                break;
            case 'image/gif':
                $sourceImage = imagecreatefromgif($imagePath);
                break;
            default:
                $sourceImage = imagecreatefromjpeg($imagePath);
        }

        // Get original dimensions
        $originalWidth = imagesx($sourceImage);
        $originalHeight = imagesy($sourceImage);

        // Calculate new dimensions (max 1200px width)
        $maxWidth = 1200;
        if ($originalWidth > $maxWidth) {
            $ratio = $maxWidth / $originalWidth;
            $newWidth = $maxWidth;
            $newHeight = (int)($originalHeight * $ratio);
        } else {
            $newWidth = $originalWidth;
            $newHeight = $originalHeight;
        }

        // Create new image with new dimensions
        $optimizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG
        imagealphablending($optimizedImage, false);
        imagesavealpha($optimizedImage, true);

        // Resize image
        imagecopyresampled(
            $optimizedImage,
            $sourceImage,
            0, 0, 0, 0,
            $newWidth,
            $newHeight,
            $originalWidth,
            $originalHeight
        );

        // Output to buffer with 80% quality
        ob_start();
        imagejpeg($optimizedImage, null, 80);
        $imageData = ob_get_clean();

        // Free memory
        imagedestroy($sourceImage);
        imagedestroy($optimizedImage);

        return $imageData;
    }

    /**
     * Delete an uploaded image.
     */
    public function delete(Request $request)
    {
        $request->validate([
            'path' => 'required|string',
        ]);

        $path = $request->input('path');

        if (Storage::disk('public')->exists($path)) {
            Storage::disk('public')->delete($path);
            return response()->json(['message' => 'Image deleted successfully']);
        }

        return response()->json(['error' => 'Image not found'], 404);
    }
}

