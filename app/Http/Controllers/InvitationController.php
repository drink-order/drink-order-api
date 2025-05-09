<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserInvitation;
use Illuminate\Http\Request;
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
     * List all invitations
     */
    public function index()
    {
        $invitations = UserInvitation::with('user')
            ->orderBy('created_at', 'desc')
            ->get();

        return response()->json(['invitations' => $invitations]);
    }

    /**
     * Create a new user with invitation
     */
    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'phone' => 'nullable|string|max:20|unique:users',
            'role' => 'required|in:admin,shop_owner,staff,user',
            'expires_at' => 'nullable|date',
        ]);

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
        $token = UserInvitation::generateToken();
        $invitation = $user->invitations()->create([
            'token' => $token,
            'role' => $request->role,
            'expires_at' => $request->expires_at,
        ]);

        // Generate invitation URL
        $invitationUrl = URL::to("/api/auth/invitation/{$token}");

        return response()->json([
            'user' => $user,
            'invitation' => $invitation,
            'password' => $password, // Only returned once for admin to share
            'invitation_url' => $invitationUrl,
        ]);
    }

    /**
     * Generate a QR code for an invitation
     */
    public function generateQrCode(string $token)
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();
        
        if (!$invitation->isValid()) {
            return response()->json([
                'message' => 'Invitation is no longer valid'
            ], 400);
        }

        $invitationUrl = URL::to("/api/auth/invitation/{$token}");
        
        try {
            // Using SVG backend which doesn't require GD or Imagick
            $renderer = new ImageRenderer(
                new RendererStyle(300, 4),
                new SvgImageBackEnd()
            );
            
            $writer = new Writer($renderer);
            $qrCode = $writer->writeString($invitationUrl);
            
            return response($qrCode)
                ->header('Content-Type', 'image/svg+xml');
        } catch (\Exception $e) {
            // Log the error
            Log::error('QR code generation failed: ' . $e->getMessage());
            
            // Use a reliable third-party service as fallback
            $qrCodeUrl = 'https://chart.googleapis.com/chart?cht=qr&chs=300x300&chl=' . urlencode($invitationUrl);
            
            // Redirect to the QR code service
            return redirect()->away($qrCodeUrl);
        }
    }

    /**
     * Revoke an invitation
     */
    public function revoke(string $token)
    {
        $invitation = UserInvitation::where('token', $token)->firstOrFail();
        $invitation->delete();

        return response()->json([
            'message' => 'Invitation revoked successfully'
        ]);
    }
}