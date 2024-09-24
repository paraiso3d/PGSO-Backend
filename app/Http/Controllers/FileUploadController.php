<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use App\Models\Upload;
use Throwable;


// THIS IS A TEST FOR FILE UPLOADING PLEASE DONT MIND THIS ONE\\



class FileUploadController extends Controller
{
    // POST: Upload a file
    public function upload(Request $request)
    {
        try {
            // Validate the file
            $request->validate([
                'file' => 'required|image|mimes:jpeg,png,jpg,gif,svg,pdf|max:5120',
            ]);

            // Handle the file upload
            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $fileName = time() . '_' . $file->getClientOriginalName();

                // Store the file in the 'uploads' folder
                $filePath = $file->storeAs('uploads', $fileName, 'public');

                // Save file metadata to the database
                $upload = new Upload();
                $upload->file_name = $fileName;
                $upload->file_path = $filePath;
                $upload->save();

                $response = [
                    'success' => true,
                    'message' => 'File uploaded successfully!',
                    'file_path' => $filePath,
                ];
                $this->logAPICalls('upload', $upload->id, $request->all(), $response);
                return response()->json($response);
            }
        } catch (Throwable $e) {
            $response = [
                'success' => false,
                'message' => 'File upload failed.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('upload', "", $request->all(), $response);
            return response()->json($response, 400);
        }
    }

    // GET: Retrieve all uploaded files
    public function getupload()
    {
        try {
            $uploads = Upload::all();

            $response = [
                'success' => true,
                'uploads' => $uploads,
            ];
            $this->logAPICalls('getupload', "", [], $response);
            return response()->json($response);
        } catch (Throwable $e) {
            $response = [
                'success' => false,
                'message' => 'Failed to retrieve uploads.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('getupload', "", [], $response);
            return response()->json($response, 500);
        }
    }

    // GET: Retrieve a specific file's details
    public function show($id)
    {
        try {
            $upload = Upload::find($id);

            if ($upload) {
                $response = [
                    'success' => true,
                    'upload' => $upload,
                ];
                $this->logAPICalls('show', $id, [], $response);
                return response()->json($response);
            }

            $response = [
                'success' => false,
                'message' => 'File not found.',
            ];
            $this->logAPICalls('show', $id, [], $response);
            return response()->json($response, 404);
        } catch (Throwable $e) {
            $response = [
                'success' => false,
                'message' => 'Error retrieving file.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('show', $id, [], $response);
            return response()->json($response, 500);
        }
    }

    // PUT: Update file metadata
    public function updateupload(Request $request, $id)
    {
        try {
            $upload = Upload::find($id);

            if ($upload) {
                $request->validate([
                    'file_name' => 'required|string',
                ]);

                $upload->file_name = $request->input('file_name');
                $upload->save();

                $response = [
                    'success' => true,
                    'message' => 'File metadata updated successfully!',
                    'upload' => $upload,
                ];
                $this->logAPICalls('updateupload', $id, $request->all(), $response);
                return response()->json($response);
            }

            $response = [
                'success' => false,
                'message' => 'File not found.',
            ];
            $this->logAPICalls('updateupload', $id, [], $response);
            return response()->json($response, 404);
        } catch (Throwable $e) {
            $response = [
                'success' => false,
                'message' => 'Failed to update file metadata.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('updateupload', $id, $request->all(), $response);
            return response()->json($response, 500);
        }
    }

    // DELETE: Delete a file
    public function deleteupload($id)
    {
        try {
            $upload = Upload::find($id);

            if ($upload) {
                // Delete file from storage
                Storage::disk('public')->delete($upload->file_path);

                // Delete file metadata from the database
                $upload->delete();

                $response = [
                    'success' => true,
                    'message' => 'File deleted successfully!',
                ];
                $this->logAPICalls('deleteupload', $id, [], $response);
                return response()->json($response);
            }

            $response = [
                'success' => false,
                'message' => 'File not found.',
            ];
            $this->logAPICalls('deleteupload', $id, [], $response);
            return response()->json($response, 404);
        } catch (Throwable $e) {
            $response = [
                'success' => false,
                'message' => 'Failed to delete the file.',
                'error' => $e->getMessage(),
            ];
            $this->logAPICalls('deleteupload', $id, [], $response);
            return response()->json($response, 500);
        }
    }

    // Log all API calls.
    public function logAPICalls(string $methodName, string $userId, array $param, array $resp)
    {
        try {
            \App\Models\ApiLog::create([
                'method_name' => $methodName,
                'user_id' => $userId,
                'api_request' => json_encode($param),
                'api_response' => json_encode($resp),
            ]);
        } catch (Throwable $e) {
            // Handle logging failure (optional)
            return false;
        }
        return true;
    }
}
