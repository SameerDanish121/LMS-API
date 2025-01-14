<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Exception;
use Illuminate\Support\Facades\Response;
use ZipArchive;
use Illuminate\Support\Facades\File;

class FileHandler extends Model
{
    public static function storeFile($fileName, $remainingDirectory, $file)
    {
        try {
            $baseDirectory = 'storage/BIIT';
            $getfileExtension = $file->getClientOriginalExtension();
            $directoryPath = $baseDirectory . '/' . $remainingDirectory;
            $storagePath = public_path($directoryPath);
            if (!File::exists($storagePath)) {
                File::makeDirectory($storagePath, 0777, true);
            }
            $filePath = 'storage/BIIT/' . $remainingDirectory;
             $file->move($storagePath, $fileName . '.' . $getfileExtension);
            return $filePath . '/' . $fileName . '.' . $getfileExtension;
        }catch (Exception $e) {
            throw new Exception('Error storing file: ' . $e->getMessage());
        }
    }
    public static function getFileByPath($originalPath = null)
    {
        if(!$originalPath){
            return null;
        }
        if (file_exists(public_path($originalPath))) {
            $imageContent = file_get_contents(public_path($originalPath));
            return base64_encode($imageContent);
        } else {
            return null;
        }
    }
    public static function deleteFileByPath($filePath)
    {
        try {
            if (file_exists(public_path($filePath))) {

                unlink(public_path($filePath));
                return 'File Deleted';
            } else {

                return 'File does not exist.';
            }
        } catch (Exception $e) {
            return 'Error deleting file: ';
        }
    }

    public static function getFolderInfo($basePath=null)
    {
        try {
            if (!$basePath) {
                $basePath = 'storage';
            }else{
                $basePath = "storage/{$basePath}";
            }
            $path = public_path($basePath);
            if (!File::exists($path)) {
                throw new Exception("The base directory does not exist: {$basePath}");
            }
            $folderDetails = self::scanFolder($path, $basePath);

            return response()->json([
                'success' => true,
                'base_path' => $basePath,
                'folder_details' => $folderDetails,
            ]);
        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    private static function scanFolder($folderPath, $basePath)
    {
        $folders = [];
        $subFolders = File::directories($folderPath);
        foreach ($subFolders as $subFolder) {
            $folderName = basename($subFolder);
            $folderSize = self::calculateFolderSize($subFolder);

            $formattedSize = self::formatSize($folderSize);
            $subFolderDetails = self::scanFolder($subFolder, $basePath);
            $relativePath = str_replace(public_path() . '/', '', $subFolder);
            $path = $relativePath;
            $trimmedPath = strstr($path, 'storage\\', false);
            $trimmedPath = ltrim($trimmedPath, 'storage\\');
            $trimmedPath = str_replace('\\', '/', $trimmedPath);
            $relativePath = $trimmedPath;
            $folders[] = [
                'folder_name' => $folderName,
                'path' => $relativePath,
                'size' => $formattedSize,
                'sub_folders' => $subFolderDetails,
            ];
        }
        return $folders;
    }
    private static function calculateFolderSize($folderPath)
    {
        $size = 0;
        foreach (File::files($folderPath) as $file) {
            $size += File::size($file);
        }
        foreach (File::directories($folderPath) as $subFolder) {
            $size += self::calculateFolderSize($subFolder);
        }

        return $size;
    }

    private static function formatSize($sizeInBytes)
    {
        if ($sizeInBytes >= (1024 ** 3)) {
            return round($sizeInBytes / (1024 ** 3), 2) . ' GB';
        } elseif ($sizeInBytes >= (1024 ** 2)) {
            return round($sizeInBytes / (1024 ** 2), 2) . ' MB';
        } else {
            return round($sizeInBytes / 1024, 2) . ' KB';
        }
    }
    public static function deleteFolder($relativeFolderPath)
    {
        try {
            $fullFolderPath = public_path('storage/' . $relativeFolderPath);
            if (File::exists($fullFolderPath)) {
                $size=self::calculateFolderSize($fullFolderPath);
                $size=self::formatSize($size);
                File::deleteDirectory($fullFolderPath);
                return $size;
            }else{
                throw new Exception('No Folder Exsist in Base Directory with provided path');
            }
        } catch (Exception $e) {
            return false;
        }
    }



    //////////////////////////////////////////////////////////Faltu Bakwas//////////////////////////////////////////////

    public static function sendDirectoryAsZip($directory)
    {
        try {
            $directoryPath = public_path($directory);
            if (!File::exists($directoryPath)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Directory does not exist.'
                ]);
            }
            $zipFileName = 'directory_' . time() . '.zip';
            $zipFilePath = public_path('temp/' . $zipFileName);
            $zip = new ZipArchive;
            if ($zip->open($zipFilePath, ZipArchive::CREATE) === TRUE) {
                self::addDirectoryToZip($zip, $directoryPath, basename($directoryPath));
                $zip->close();
            } else {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to create ZIP file.'
                ]);
            }
            return response()->download($zipFilePath)->deleteFileAfterSend(true);

        } catch (Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error: ' . $e->getMessage()
            ]);
        }
    }

    private static function addDirectoryToZip($zip, $dirPath, $baseFolder)
    {
        $files = File::allFiles($dirPath);
        foreach ($files as $file) {
            $zip->addFile($file->getRealPath(), $baseFolder . '/' . $file->getRelativePathname());
        }
        $directories = File::directories($dirPath);
        foreach ($directories as $directory) {
            self::addDirectoryToZip($zip, $directory, $baseFolder);
        }
    }

}





