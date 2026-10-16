import React, { useEffect, useState } from "react";
import { useNavigate } from "react-router-dom";
import { useAuth } from "@/context/AuthContext";
import { Button } from "@/components/ui/button";
import { Input } from "@/components/ui/input";
import { useToast } from "@/components/ui/use-toast";
import { Loader2, RefreshCw, ArrowLeft, AlertTriangle } from "lucide-react";
import { motion } from "framer-motion";

const OTPVerificationScreen = () => {
  const [otp, setOtp] = useState("");
  const [isLoading, setIsLoading] = useState(false);
  const [statusMessage, setStatusMessage] = useState("");
  const [timeLeft, setTimeLeft] = useState(300);
  const [attempts, setAttempts] = useState(5);
  const [canResend, setCanResend] = useState(false);

  const { verifyOTP, resendOTP, logout, user, getProfile } = useAuth();
  const navigate = useNavigate();
  const { toast } = useToast();

  const SECURITY_IMAGE =
    "https://horizons-cdn.hostinger.com/81ef3422-3855-479e-bfe8-28a4ceb0df39/0e6288c4dcb5f1a443d2d1c74e86297f.png";

  useEffect(() => {
    if (!("OTPCredential" in window) || !navigator.credentials) return undefined;
    const ac = new AbortController();
    const timer = setTimeout(() => ac.abort(), 60000);
    navigator.credentials.get({ otp: { transport: ["sms"] }, signal: ac.signal })
      .then((otpCredential) => {
        if (otpCredential && otpCredential.code) {
          setOtp(String(otpCredential.code).replace(/\D/g, "").slice(0, 6));
        }
      })
      .catch(() => {});
    return () => {
      clearTimeout(timer);
      ac.abort();
    };
  }, []);

  useEffect(() => {
    if (timeLeft <= 0) {
      setCanResend(true);
      return;
    }
    const timer = setTimeout(() => setTimeLeft((t) => t - 1), 1000);
    return () => clearTimeout(timer);
  }, [timeLeft]);

  const formatTime = (seconds) => {
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return `${mins}:${secs.toString().padStart(2, "0")}`;
  };

  // Task 5: Implement redirectBasedOnRole
  const redirectBasedOnRole = (role) => {
    const r = String(role || "").toLowerCase();
    let destination = '/';
    
    switch (r) {
        case 'admin':
        case 'super_admin':
        case 'director':
        case 'manager':
            destination = '/admin/dashboard';
            break;
        case 'student':
            destination = '/student/dashboard';
            break;
        case 'shareholder':
            destination = '/shareholder/dashboard';
            break;
        case 'applicant':
            destination = '/applicant-dashboard';
            break;
        case 'task_assignee':
        case 'customer':
            destination = '/user/tasks/pending-acceptances';
            break;
        default:
            destination = '/';
    }
    return destination;
  };

  // Task 5 & 6: Update redirect logic after OTP verification and add logs
  const handleVerifyOTP = async (e) => {
    e.preventDefault();
    const cleanOtp = otp.replace(/\D/g, "");
    
    console.log(`[OTP Verification] Submit Attempt. phone: ${user?.phone || 'unknown'}, isVerifying: true, otpCode: ${cleanOtp}, timestamp: ${new Date().toISOString()}`);

    if (cleanOtp.length !== 6) return;

    setIsLoading(true);
    setStatusMessage("Verifying code...");

    try {
      // 1. Verify OTP code
      const result = await verifyOTP(cleanOtp);

      // 2. Log verification result
      console.log(`[OTP Verification] Result: success=${result.success}, error=${result.error || 'none'}`);

      if (!result.success) {
        setAttempts((prev) => Math.max(prev - 1, 0));
        throw new Error(result.error || "Verification failed");
      }

      // 3. Profile is returned by verifyOTP on success (Context handles setting state)
      const profile = result.profile;

      if (!profile) {
        setStatusMessage("Loading profile...");
        const fetchedProfile = await getProfile(user.id);
        if (!fetchedProfile) throw new Error("Profile not found.");
        
        handleSuccess(fetchedProfile);
      } else {
        handleSuccess(profile);
      }

    } catch (err) {
      console.error("[OTP Verification] Error caught:", err);
      toast({
        title: "Verification Failed",
        description: err.message,
        variant: "destructive",
      });
    } finally {
      setIsLoading(false);
      setStatusMessage("");
    }
  };

  const handleSuccess = (profileData) => {
    // 4. Update auth context with user ID and role happens inside verifyOTP/getProfile internally,
    // we now determine the route.
    console.log(`[OTP Verification] Auth Context Updated for user: ${profileData.id}, role: ${profileData.role}`);
    
    // First-login guests must set their own credentials/profile before continuing.
    const destination = profileData.must_change_credentials
      ? '/complete-profile'
      : redirectBasedOnRole(profileData.role);
    
    // Detailed log for redirect decision
    console.log(`[OTP Verification] Redirect Decision -> Role: ${profileData.role}, Destination: ${destination}, UserId: ${profileData.id}, Timestamp: ${new Date().toISOString()}`);

    toast({
        title: "Access Granted",
        description: `Welcome back, ${profileData.full_name || "User"}`,
        className: "bg-green-600 text-white border-none",
    });

    navigate(destination, { replace: true, state: { verifiedRole: profileData.role } });
  };

  const handleResend = async () => {
    if (!canResend) return;
    setIsLoading(true);
    setStatusMessage("Resending code...");
    try {
      const result = await resendOTP();
      if (result.success) {
        toast({ title: "OTP Resent", description: "Check WhatsApp for code." });
        setTimeLeft(300);
        setCanResend(false);
        setAttempts(5);
        setOtp("");
      } else {
        toast({ title: "Error", description: result.error, variant: "destructive" });
      }
    } catch (e) {
      toast({ title: "Error", description: "Failed to resend.", variant: "destructive" });
    } finally {
      setIsLoading(false);
      setStatusMessage("");
    }
  };

  const handleBackToLogin = async () => {
    await logout();
    navigate("/login");
  };

  if (!user) {
    return (
      <div className="flex flex-col items-center justify-center min-h-screen bg-gray-50">
         <AlertTriangle className="w-12 h-12 text-yellow-500 mb-4" />
         <p className="text-lg font-semibold">Session Expired</p>
         <Button onClick={() => navigate('/login')} className="mt-4">Back to Login</Button>
      </div>
    );
  }

  const maskedPhone = user.phone 
    ? user.phone.replace(/(\d{3})\d+(\d{2})/, "$1****$2") 
    : "your phone";

  return (
    <div className="min-h-screen flex items-center justify-center bg-gradient-to-br from-[#003D82] to-[#001f42] p-4">
      <motion.div
        initial={{ opacity: 0, scale: 0.95 }}
        animate={{ opacity: 1, scale: 1 }}
        className="max-w-4xl w-full bg-white rounded-2xl shadow-2xl overflow-hidden flex flex-col md:flex-row"
      >
        <div className="md:w-1/2 bg-gray-50 flex items-center justify-center p-8 border-r border-gray-100">
          <div className="text-center">
            <img
              src={SECURITY_IMAGE}
              alt="Secure Verification"
              className="w-full max-w-[280px] mx-auto mb-6 object-contain drop-shadow-md"
            />
            <h3 className="text-xl font-bold text-[#003D82]">Secure Authentication</h3>
            <p className="text-gray-500 text-sm mt-2 max-w-xs mx-auto">
              We use Two-Factor Authentication to ensure your account remains safe.
            </p>
          </div>
        </div>

        <div className="md:w-1/2 p-8 md:p-12 flex flex-col justify-center">
          <div className="mb-8">
            <h2 className="text-2xl font-bold text-gray-900">Verify It's You</h2>
            <p className="text-gray-500 mt-2">
              Enter the 6-digit code sent to <br />
              <span className="font-semibold text-gray-800 bg-gray-100 px-2 py-0.5 rounded">
                {maskedPhone}
              </span>
            </p>
          </div>

          <form onSubmit={handleVerifyOTP} className="space-y-6">
            <Input
              type="text"
              inputMode="numeric"
              autoComplete="one-time-code"
              maxLength="6"
              placeholder="000000"
              className="text-center text-4xl tracking-[0.5em] h-20 font-bold border-2 border-gray-200 focus:border-[#D4AF37] focus:ring-[#D4AF37]/20 rounded-xl"
              value={otp}
              onChange={(e) => setOtp(e.target.value.replace(/[^0-9]/g, ""))}
              disabled={isLoading || attempts <= 0}
            />

            <div className="flex justify-between items-center text-sm">
              <div className="flex items-center gap-2">
                <span className="text-gray-500">Expires in:</span>
                <span className={`font-mono font-bold ${timeLeft < 60 ? "text-red-500" : "text-[#003D82]"}`}>
                  {formatTime(timeLeft)}
                </span>
              </div>
              <span className={attempts < 3 ? "text-red-500 font-bold" : "text-gray-500"}>
                {attempts} attempts left
              </span>
            </div>

            <Button
              type="submit"
              className="w-full h-12 bg-[#003D82] hover:bg-[#002855] text-white font-bold text-lg shadow-md"
              disabled={isLoading || otp.length < 6 || attempts <= 0}
            >
              {isLoading ? (
                <div className="flex items-center">
                  <Loader2 className="w-5 h-5 animate-spin mr-2" />
                  {statusMessage || "Processing..."}
                </div>
              ) : (
                "Verify OTP"
              )}
            </Button>
          </form>

          <div className="mt-8 flex flex-col items-center gap-4">
            <Button
              variant="ghost"
              size="sm"
              className="text-[#003D82] hover:bg-blue-50 w-full"
              onClick={handleResend}
              disabled={!canResend || isLoading}
            >
              <RefreshCw className="w-4 h-4 mr-2" />
              {canResend ? "Resend Code" : `Resend available in ${timeLeft}s`}
            </Button>

            <button
              onClick={handleBackToLogin}
              className="flex items-center text-gray-400 hover:text-gray-600 text-sm transition-colors mt-4"
            >
              <ArrowLeft className="w-4 h-4 mr-1" /> Back to Login
            </button>
          </div>
        </div>
      </motion.div>
    </div>
  );
};

export default OTPVerificationScreen;