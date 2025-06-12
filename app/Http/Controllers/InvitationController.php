<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;

class InvitationController extends Controller
{
    /**
     * List all invitations - returns direct array for frontend compatibility
     */
    public function index()
    {
        $invitations = UserInvitation::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        // Return array directly, not wrapped in object to fix frontend data.map error
        return response()->json($invitations);
    }

    /**
     * Create a new invitation for admin panel (table-based guest access)
     */
    public function createInvitation(Request $request)
    {
        $request->validate([
            'table_number' => 'required|string|max:10',
        ]);

        try {
            // Check if table already has an active invitation
            $existingInvitation = UserInvitation::where('table_number', $request->table_number)
                ->where('expires_at', '>', now())
                ->first();

            if ($existingInvitation) {
                return response()->json([
                    'message' => 'Table ' . $request->table_number . ' already has an active invitation',
                    'existing_invitation' => $existingInvitation
                ], 409);
            }

            // Generate a unique token
            $token = $this->generateUniqueToken();
            
            $invitation = UserInvitation::create([
                'token' => $token,
                'table_number' => $request->table_number,
                'user_id' => $request->user()->id, // The admin who created it
                'role' => 'guest',
                'expires_at' => now()->addHours(24),
            ]);

            // Generate frontend URL with table number
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $tableParam = '&table=' . urlencode($request->table_number);
            $invitationUrl = $frontendUrl . '/guest-login?token=' . $token . $tableParam;

            return response()->json([
                'token' => $token,
                'table_number' => $request->table_number,
                'invitation_url' => $invitationUrl,
                'expires_at' => $invitation->expires_at,
                'created_at' => $invitation->created_at,
                'user_id' => $invitation->user_id
            ], 201);
            
        } catch (\Exception $e) {
            Log::error('Failed to create invitation: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Create a new user with invitation (legacy method for user management)
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'role' => 'required|in:admin,shop_owner,staff,user',
            'expires_at' => 'nullable|date',
            'table_number' => 'nullable|string|max:10', // Optional for staff invitations
        ]);

        try {
            // Create a random password
            $password = Str::random(12);

            // Create the user
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'phone' => $request->phone,
                'password' => Hash::make($password),
                'role' => $request->role,
            ]);

            // Create invitation
            $token = $this->generateUniqueToken();
            $invitation = $user->invitations()->create([
                'token' => $token,
                'role' => $request->role,
                'table_number' => $request->table_number,
                'user_id' => $request->user()->id, // The admin who created it
                'expires_at' => $request->expires_at ?? now()->addDays(7), // Default 7 days for user invitations
            ]);

            // Generate invitation URL
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $tableParam = $request->table_number ? '&table=' . urlencode($request->table_number) : '';
            $invitationUrl = $frontendUrl . '/invitation/' . $token . $tableParam;

            return response()->json([
                'user' => $user,
                'invitation' => $invitation,
                'password' => $password, // Only returned once for admin to share
                'invitation_url' => $invitationUrl,
            ], 201);

        } catch (\Exception $e) {
            Log::error('Failed to create user invitation: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to create user invitation: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a QR code for an invitation
     */
    public function generateQrCode(string $token)
    {
        try {
            $invitation = UserInvitation::where('token', $token)->firstOrFail();
            
            if (!$invitation->isValid()) {
                return response()->json([
                    'message' => 'Invitation is no longer valid'
                ], 400);
            }

            // Generate frontend URL with table number
            $frontendUrl = config('app.frontend_url', 'http://localhost:3000');
            $tableParam = $invitation->table_number ? '&table=' . urlencode($invitation->table_number) : '';
            $invitationUrl = $frontendUrl . '/guest-login?token=' . $token . $tableParam;
            
            Log::info('Generating QR code for URL: ' . $invitationUrl);

            try {
                // Using SVG backend which doesn't require GD or Imagick
                $renderer = new ImageRenderer(
                    new RendererStyle(300, 4),
                    new SvgImageBackEnd()
                );
                
                $writer = new Writer($renderer);
                $qrCode = $writer->writeString($invitationUrl);
                
                return response($qrCode)
                    ->header('Content-Type', 'image/svg+xml')
                    ->header('Cache-Control', 'no-cache, no-store, must-revalidate')
                    ->header('Pragma', 'no-cache')
                    ->header('Expires', '0');

            } catch (\Exception $e) {
                // Log the error
                Log::error('QR code generation failed: ' . $e->getMessage());
                
                // Use a reliable third-party service as fallback
                $qrCodeUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=' . urlencode($invitationUrl);
                
                // Redirect to the QR code service
                return redirect()->away($qrCodeUrl);
            }

        } catch (\Exception $e) {
            Log::error('QR code request failed: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to generate QR code'
            ], 500);
        }
    }

    /**
     * Get invitation details
     */
    public function show(string $token)
    {
        try {
            $invitation = UserInvitation::with('user')
                ->where('token', $token)
                ->firstOrFail();

            return response()->json([
                'invitation' => $invitation,
                'is_valid' => $invitation->isValid(),
                'table_number' => $invitation->table_number,
                'expires_at' => $invitation->expires_at,
                'created_at' => $invitation->created_at,
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'message' => 'Invitation not found'
            ], 404);
        }
    }

    /**
     * Revoke an invitation
     */
    public function revoke(string $token)
    {
        try {
            $invitation = UserInvitation::where('token', $token)->firstOrFail();
            
            // Store info for response
            $tableNumber = $invitation->table_number;
            
            $invitation->delete();

            return response()->json([
                'message' => 'Invitation revoked successfully',
                'table_number' => $tableNumber
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to revoke invitation: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to revoke invitation'
            ], 500);
        }
    }

    /**
     * Get all active invitations for a specific table
     */
    public function getTableInvitations(string $tableNumber)
    {
        try {
            $invitations = UserInvitation::where('table_number', $tableNumber)
                ->where('expires_at', '>', now())
                ->with('user')
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json($invitations);

        } catch (\Exception $e) {
            Log::error('Failed to get table invitations: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to get table invitations'
            ], 500);
        }
    }

    /**
     * Bulk revoke invitations (useful for clearing expired invitations)
     */
    public function bulkRevoke(Request $request)
    {
        $request->validate([
            'tokens' => 'required|array',
            'tokens.*' => 'string',
        ]);

        try {
            $deleted = UserInvitation::whereIn('token', $request->tokens)->delete();

            return response()->json([
                'message' => "Successfully revoked {$deleted} invitation(s)",
                'revoked_count' => $deleted
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to bulk revoke invitations: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to revoke invitations'
            ], 500);
        }
    }

    /**
     * Clean up expired invitations (can be called via cron job)
     */
    public function cleanupExpired()
    {
        try {
            $deleted = UserInvitation::where('expires_at', '<', now())->delete();

            Log::info("Cleaned up {$deleted} expired invitations");

            return response()->json([
                'message' => "Cleaned up {$deleted} expired invitation(s)",
                'deleted_count' => $deleted
            ]);

        } catch (\Exception $e) {
            Log::error('Failed to cleanup expired invitations: ' . $e->getMessage());
            return response()->json([
                'message' => 'Failed to cleanup expired invitations'
            ], 500);
        }
    }

    /**
     * Generate a unique token for invitations
     */
    private function generateUniqueToken()
    {
        do {
            $token = Str::random(32);
        } while (UserInvitation::where('token', $token)->exists());

        return $token;
    }
}