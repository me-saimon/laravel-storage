# Laravel Storage File Helper

A small Laravel helper for common file operations: upload, upload with a custom name, replace an old file, delete files, check file existence, generate file URLs, and move files directly into the public directory when needed.

The helper lives at:

```text
app/Helpers/Filehelpers.php
```

The class name is:

```php
App\Helpers\FileHelper
```

## Features

- Upload files to any Laravel filesystem disk.
- Upload files with custom file names.
- Automatically create unique file names.
- Disable unique naming when you need a fixed file name.
- Replace old files during upload.
- Delete files from Laravel storage.
- Check whether files exist.
- Generate public URLs for disks that support URLs.
- Upload directly to the `public` directory.
- Delete and check files inside the `public` directory.
- Normalize paths to avoid accidental leading slash, backslash, `.` or `..` issues.

## Requirements

- Laravel application with the `Storage` facade available.
- A valid filesystem disk in `config/filesystems.php`.
- For public storage URLs, run:

```bash
php artisan storage:link
```

## Filesystem Configuration

This project includes these disks in `config/filesystems.php`:

```php
'local' => [
    'driver' => 'local',
    'root' => storage_path('app'),
],

'public' => [
    'driver' => 'local',
    'root' => storage_path('app/public'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],

'root' => [
    'driver' => 'local',
    'root' => base_path('../storage'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],

'base' => [
    'driver' => 'local',
    'root' => base_path('storage'),
    'url' => env('APP_URL').'/storage',
    'visibility' => 'public',
],
```

The default disk is controlled by:

```env
FILESYSTEM_DRIVER=local
```

For public browser-accessible files, use the `public` disk:

```env
FILESYSTEM_DRIVER=public
APP_URL=http://127.0.0.1:8000
```

Or pass the disk explicitly:

```php
FileHelper::upload($request->file('avatar'), 'avatars', null, 'public');
```

## Importing The Helper

Use the helper in controllers like this:

```php
use App\Helpers\FileHelper;
```

Example controller method:

```php
public function store(Request $request)
{
    $request->validate([
        'image' => ['required', 'image', 'max:2048'],
    ]);

    $file = FileHelper::upload(
        $request->file('image'),
        'uploads/images',
        null,
        'public'
    );

    // Store $file['path'] in your database.
}
```

## Return Format

Upload methods return an array like this:

```php
[
    'path' => 'uploads/images/photo-1710000000-a1b2c3d4.jpg',
    'filename' => 'photo-1710000000-a1b2c3d4.jpg',
    'extension' => 'jpg',
    'original_name' => 'Photo.jpg',
    'mime_type' => 'image/jpeg',
    'size' => 153924,
    'disk' => 'public',
    'url' => 'http://127.0.0.1:8000/storage/uploads/images/photo-1710000000-a1b2c3d4.jpg',
]
```

`uploadToPublic()` returns the same structure except it does not include a `disk` key.

## Method Reference

### Upload A File

```php
FileHelper::upload($file, $folder = 'uploads', $customFileName = null, $disk = null, array $options = []);
```

Example:

```php
$uploaded = FileHelper::upload(
    $request->file('document'),
    'documents',
    null,
    'public'
);
```

Use case: normal image, PDF, document, or attachment upload.

### Upload With A Custom Name

```php
FileHelper::uploadAs($file, $folder, $fileName, $disk = null, array $options = []);
```

Example:

```php
$uploaded = FileHelper::uploadAs(
    $request->file('avatar'),
    'avatars',
    'user-'.$user->id,
    'public'
);
```

If the file is `profile.png`, the final name will look like:

```text
user-15-1710000000-a1b2c3d4.png
```

Use case: user avatars, product images, event banners, profile photos, generated reports.

### Upload With A Fixed Name

By default, the helper adds a timestamp and random string. To keep the exact custom name, set `unique` to `false`.

```php
$uploaded = FileHelper::uploadAs(
    $request->file('logo'),
    'branding',
    'site-logo.png',
    'public',
    ['unique' => false, 'overwrite' => true]
);
```

Use case: files that should always have the same URL, such as `site-logo.png`, `favicon.ico`, or a downloadable catalog.

### Replace An Existing File

```php
FileHelper::replace($file, $oldFilePath = null, $folder = 'uploads', $customFileName = null, $disk = null, array $options = []);
```

Example:

```php
$uploaded = FileHelper::replace(
    $request->file('avatar'),
    $user->avatar,
    'avatars',
    'user-'.$user->id,
    'public'
);

$user->update([
    'avatar' => $uploaded['path'],
]);
```

Use case: updating a profile photo, replacing an event image, replacing a product document, or removing an old file when uploading a new one.

### Delete A Storage File

```php
FileHelper::delete($path, $disk = null);
```

Example:

```php
FileHelper::delete($user->avatar, 'public');
```

Use case: deleting a file when a database record is deleted.

```php
public function destroy(User $user)
{
    FileHelper::delete($user->avatar, 'public');
    $user->delete();

    return redirect()->back();
}
```

### Check If A Storage File Exists

```php
FileHelper::exists($path, $disk = null);
```

Example:

```php
if (FileHelper::exists($user->avatar, 'public')) {
    // File exists.
}
```

Use case: showing fallback images or preventing broken download links.

### Get A File URL

```php
FileHelper::url($path, $disk = null);
```

Example:

```php
$avatarUrl = FileHelper::url($user->avatar, 'public');
```

Use case: displaying uploaded files in Blade, API responses, or admin panels.

```php
return response()->json([
    'avatar' => FileHelper::url($user->avatar, 'public'),
]);
```

### Upload Directly To Public Directory

```php
FileHelper::uploadToPublic($file, $folder = 'uploads', $customFileName = null, $oldFilePath = null, array $options = []);
```

Example:

```php
$uploaded = FileHelper::uploadToPublic(
    $request->file('image'),
    'uploads/admin',
    'admin-photo'
);
```

Use case: older Laravel projects that store files directly under `public/uploads` instead of `storage/app/public`.

Recommended for new code: prefer the `public` disk with `FileHelper::upload()`.

### Replace A Public Directory File

```php
$uploaded = FileHelper::uploadToPublic(
    $request->file('image'),
    'uploads/admin',
    'admin-photo',
    $oldImagePath
);
```

Use case: replacing legacy files stored in `public/uploads`.

### Delete A Public Directory File

```php
FileHelper::deletePublic('uploads/admin/photo.jpg');
```

Use case: deleting files that were moved using `uploadToPublic()`.

### Check If A Public Directory File Exists

```php
if (FileHelper::existsPublic('uploads/admin/photo.jpg')) {
    // File exists.
}
```

Use case: showing fallback content for legacy public uploads.

### Build A File Name Only

```php
FileHelper::makeFileName($file, $customFileName = null, array $options = []);
```

Example:

```php
$filename = FileHelper::makeFileName(
    $request->file('image'),
    'event-banner'
);
```

Use case: generating a clean slugged file name before custom file handling.

## Options

The upload methods accept an `$options` array.

### `unique`

Controls whether the helper adds a timestamp and random string.

```php
['unique' => false]
```

Default:

```php
true
```

### `overwrite`

Controls whether a file with the same name should be overwritten.

```php
['overwrite' => true]
```

Default:

```php
false
```

When `overwrite` is false, the helper appends `-1`, `-2`, etc. if the file name already exists.

### `visibility`

Sets storage visibility when using Laravel disks.

```php
['visibility' => 'public']
```

Use case: making S3 or public disk files browser-readable.

### `old_file`

Deletes an old storage file before uploading the new one.

```php
[
    'old_file' => $user->avatar,
]
```

This is used internally by `replace()`.

## Common Use Cases

### User Avatar Upload

```php
public function updateAvatar(Request $request)
{
    $request->validate([
        'avatar' => ['required', 'image', 'max:2048'],
    ]);

    $uploaded = FileHelper::replace(
        $request->file('avatar'),
        auth()->user()->avatar,
        'avatars',
        'user-'.auth()->id(),
        'public'
    );

    auth()->user()->update([
        'avatar' => $uploaded['path'],
    ]);

    return back()->with('success', 'Avatar updated successfully.');
}
```

### Product Image Upload

```php
public function store(Request $request)
{
    $request->validate([
        'name' => ['required', 'string'],
        'image' => ['required', 'image', 'max:4096'],
    ]);

    $uploaded = FileHelper::uploadAs(
        $request->file('image'),
        'products',
        $request->name,
        'public'
    );

    Product::create([
        'name' => $request->name,
        'image' => $uploaded['path'],
    ]);

    return redirect()->route('products.index');
}
```

### PDF Upload

```php
public function uploadPdf(Request $request)
{
    $request->validate([
        'pdf' => ['required', 'file', 'mimes:pdf', 'max:10240'],
    ]);

    $uploaded = FileHelper::upload(
        $request->file('pdf'),
        'documents/pdfs',
        null,
        'public'
    );

    return response()->json($uploaded);
}
```

### Multiple File Upload

```php
public function uploadGallery(Request $request)
{
    $request->validate([
        'images' => ['required', 'array'],
        'images.*' => ['image', 'max:4096'],
    ]);

    $files = [];

    foreach ($request->file('images') as $image) {
        $files[] = FileHelper::upload($image, 'gallery', null, 'public');
    }

    return response()->json($files);
}
```

### Delete File When Deleting Model

```php
public function destroy(Product $product)
{
    FileHelper::delete($product->image, 'public');

    $product->delete();

    return redirect()->route('products.index');
}
```

### Return Image URL In API

```php
return response()->json([
    'id' => $user->id,
    'name' => $user->name,
    'avatar_url' => FileHelper::url($user->avatar, 'public'),
]);
```

### Use A Fallback Image

```php
$avatar = FileHelper::exists($user->avatar, 'public')
    ? FileHelper::url($user->avatar, 'public')
    : asset('images/default-avatar.png');
```

### Upload To S3

Configure the `s3` disk in `.env`:

```env
FILESYSTEM_CLOUD=s3
AWS_ACCESS_KEY_ID=your-key
AWS_SECRET_ACCESS_KEY=your-secret
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=your-bucket
AWS_URL=
AWS_ENDPOINT=
```

Upload to S3:

```php
$uploaded = FileHelper::upload(
    $request->file('document'),
    'documents',
    null,
    's3',
    ['visibility' => 'public']
);
```

## Blade Examples

### Show Uploaded Image

```blade
@if ($user->avatar)
    <img src="{{ FileHelper::url($user->avatar, 'public') }}" alt="{{ $user->name }}">
@endif
```

If you do not want to import the helper in Blade, pass the URL from the controller.

### Download Link

```blade
<a href="{{ FileHelper::url($document->path, 'public') }}" target="_blank">
    View document
</a>
```

## Best Practices

- Store only the `path` in the database, not the full URL.
- Generate URLs at display time using `FileHelper::url()`.
- Use the `public` disk for files users should access in the browser.
- Use the `local` disk for private files.
- Validate file type and size before upload.
- Prefer `replace()` when updating a single-file field.
- Prefer `delete()` inside model delete flows to avoid orphaned files.
- Use `uploadToPublic()` only for legacy projects that already store files under `public/uploads`.

## Troubleshooting

### Uploaded File URL Returns 404

Run:

```bash
php artisan storage:link
```

Make sure `APP_URL` is correct in `.env`.

### URL Is Null

`FileHelper::url()` returns `null` if the disk does not support public URLs. Use a disk with a configured `url`, such as `public`.

### File Is Uploaded But Not Public

Use the `public` disk:

```php
FileHelper::upload($file, 'uploads', null, 'public');
```

Or pass visibility:

```php
FileHelper::upload($file, 'uploads', null, 's3', [
    'visibility' => 'public',
]);
```

### Custom Name Still Gets Extra Characters

That is the default unique naming behavior. Disable it when you need an exact file name:

```php
FileHelper::uploadAs($file, 'branding', 'logo.png', 'public', [
    'unique' => false,
    'overwrite' => true,
]);
```

## Notes

`FileHelper::upload()` uses Laravel storage disks. This is the recommended approach for new code.

`FileHelper::uploadToPublic()` moves files directly into the public directory. Use it when maintaining older code that expects files under paths like `public/uploads`.
