# Checkpoint Evidence Storage

## Overview
Photos and signatures captured during checkpoint completion are stored as **private files** on the server, with only file paths saved in the database. This approach is more efficient than storing base64 data directly in the database.

## Storage Location
Files are stored in the **private** Laravel storage disk:
- **Photos**: `storage/app/private/checkpoints/photos/`
- **Signatures**: `storage/app/private/checkpoints/signatures/`

## File Naming Convention
Files are named with the following pattern:
```
job_{jobId}_{type}_{timestamp}.{extension}

Examples:
- job_123_photo_20260504143022.jpg
- job_456_signature_20260504143045.png
```

## Database Fields
- `job_checkpoints.photo_path` - Relative path to photo file
- `job_checkpoints.signature_path` - Relative path to signature file

## How to Retrieve Files

### Backend (PHP)
```php
// Get checkpoint with file paths
$checkpoint = JobCheckpoint::find($checkpointId);

// Access file paths
$photoPath = $checkpoint->photo_path;        // "checkpoints/photos/job_123_photo_20260504143022.jpg"
$signaturePath = $checkpoint->signature_path; // "checkpoints/signatures/job_456_signature_20260504143045.png"

// Read file contents
if ($photoPath) {
    $file = Storage::disk('local')->get("private/{$photoPath}");
    $mimeType = Storage::disk('local')->mimeType("private/{$photoPath}");
}
```

### Frontend (JavaScript/Vue)
Use the secure routes to display images:

```javascript
// Display checkpoint photo
const photoUrl = `/jobs/checkpoint/${checkpointId}/photo`;

// Display checkpoint signature
const signatureUrl = `/jobs/checkpoint/${checkpointId}/signature`;

// In Vue template:
<img v-if="checkpoint.photo_path" 
     :src="`/jobs/checkpoint/${checkpoint.id}/photo`" 
     alt="Checkpoint photo" />

<img v-if="checkpoint.signature_path" 
     :src="`/jobs/checkpoint/${checkpoint.id}/signature`" 
     alt="Signature" />
```

## API Routes
- `GET /jobs/checkpoint/{checkpointId}/photo` - Retrieve photo file
- `GET /jobs/checkpoint/{checkpointId}/signature` - Retrieve signature file

These routes are:
- **Protected** by authentication middleware
- **Private** - files are not publicly accessible via direct URLs
- **Efficient** - serve files directly without base64 encoding

## Security Features
1. **Private Storage**: Files are stored in `storage/app/private/`, not in the public directory
2. **Authentication Required**: Routes require authentication to access
3. **Direct Access Prevention**: Files cannot be accessed via direct URLs without going through the application
4. **File Validation**: Base64 data is validated before saving

## File Size Limits
- Maximum photo size: **5MB** (enforced on frontend)
- Supported formats: **PNG, JPEG, JPG, GIF, WEBP**

## Migration Notes
- Old checkpoints with base64 data in `photo_data` and `signature_data` columns are still supported
- New checkpoints automatically use file storage
- The `photo_data` and `signature_data` columns can be used as fallback if needed

## Example: Displaying Evidence in a Detail View
```vue
<template>
  <div class="checkpoint-detail">
    <h3>{{ checkpoint.name }}</h3>
    
    <!-- Photo -->
    <div v-if="checkpoint.photo_path" class="evidence-section">
      <h4>Photo Evidence</h4>
      <img :src="`/jobs/checkpoint/${checkpoint.id}/photo`" 
           alt="Checkpoint photo"
           class="evidence-image" />
      <a :href="`/jobs/checkpoint/${checkpoint.id}/photo`" 
         target="_blank" 
         download>
        Download Photo
      </a>
    </div>
    
    <!-- Signature -->
    <div v-if="checkpoint.signature_path" class="evidence-section">
      <h4>Signature</h4>
      <img :src="`/jobs/checkpoint/${checkpoint.id}/signature`" 
           alt="Signature"
           class="evidence-image" />
    </div>
  </div>
</template>
```

## Maintenance

### Cleanup Old Files
You can create an Artisan command to clean up old checkpoint files:

```php
// Remove files for checkpoints older than 90 days
$oldCheckpoints = JobCheckpoint::where('completed_at', '<', now()->subDays(90))
    ->whereNotNull('photo_path')
    ->orWhereNotNull('signature_path')
    ->get();

foreach ($oldCheckpoints as $checkpoint) {
    if ($checkpoint->photo_path) {
        Storage::disk('local')->delete("private/{$checkpoint->photo_path}");
    }
    if ($checkpoint->signature_path) {
        Storage::disk('local')->delete("private/{$checkpoint->signature_path}");
    }
}
```

## Benefits of This Approach
1. **Smaller Database**: Files stored separately, not as base64 in DB
2. **Better Performance**: Faster queries without large text fields
3. **Efficient Transfer**: Images served directly without base64 encoding/decoding
4. **Security**: Private storage with authentication required
5. **Scalability**: Easy to move to cloud storage (S3, etc.) later if needed
