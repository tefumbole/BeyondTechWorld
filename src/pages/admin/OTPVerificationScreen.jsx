import React, { useState, useEffect } from 'react';
import { useNavigate, useLocation } from 'react-router-dom';
import { useAuth } from '@/context/AuthContext';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { useToast } from '@/components/ui/use-toast';
import { ShieldCheck, Loader2, ArrowRight, LockKeyhole, RefreshCw, AlertTriangle } from 'lucide-react';
import { motion, AnimatePresence } from 'framer-motion';

const OTPVerificationScreen = () => {
    const [otp, setOtp] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [timeLeft, setTimeLeft] = useState(300); // 5 minutes
    const [canResend, setCanResend] = useState(false);
    const [resendCooldown, setResendCooldown] = useState(60);
    const [attempts, setAttempts] = useState(5);
    const { verifyOTP, resendOTP, user, logout, otpVerified } = useAuth();
    const navigate = useNavigate();
    const location = useLocation();
    const { toast } = useToast();

    // If user is already verified, redirect to dashboard
    useEffect(() => {
        if (otpVerified) {
            navigate('/admin/dashboard', { replace: true });
        }
    }, [otpVerified, navigate]);

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

    // Timer Logic
    useEffect(() => {
        if (timeLeft > 0) {
            const timer = setTimeout(() => setTimeLeft(timeLeft - 1), 1000);
            return () => clearTimeout(timer);
        }
    }, [timeLeft]);

    // Resend Cooldown Logic
    useEffect(() => {
        if (resendCooldown > 0) {
            const timer = setTimeout(() => setResendCooldown(resendCooldown - 1), 1000);
            return () => clearTimeout(timer);
        } else {
            setCanResend(true);
        }
    }, [resendCooldown]);

    const formatTime = (seconds) => {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, '0')}`;
    };

    const handleVerify = async (e) => {
        e.preventDefault();
        if (otp.length < 6) return;
        
        setIsLoading(true);
        try {
            const result = await verifyOTP(otp);
            if (result.success) {
                toast({
                    title: "Verified Successfully",
                    description: "Access granted. Redirecting to dashboard...",
                    className: "bg-green-600 text-white border-none"
                });
                navigate('/admin/dashboard', { replace: true });
            } else {
                setAttempts(prev => Math.max(0, prev - 1));
                toast({
                    title: "Verification Failed",
                    description: result.error || "Invalid OTP code. Please try again.",
                    variant: "destructive"
                });
                if (result.error?.includes("attempts")) {
                    setAttempts(0);
                }
            }
        } catch (error) {
            console.error("Verification error:", error);
            toast({
                title: "Error",
                description: "Something went wrong. Please try again.",
                variant: "destructive"
            });
        } finally {
            setIsLoading(false);
        }
    };

    const handleResend = async () => {
        if (!canResend) return;
        setIsLoading(true);
        try {
            const result = await resendOTP();
            if (result.success) {
                toast({
                    title: "OTP Resent",
                    description: "A new code has been sent to your WhatsApp.",
                });
                setResendCooldown(60);
                setCanResend(false);
                setTimeLeft(300);
                setAttempts(5);
            } else {
                toast({
                    title: "Resend Failed",
                    description: result.error,
                    variant: "destructive"
                });
            }
        } catch (error) {
            toast({
                title: "Error",
                description: "Failed to resend code.",
                variant: "destructive"
            });
        } finally {
            setIsLoading(false);
        }
    };

    if (!user) {
        // Fallback if accessed directly without login
        return (
            <div className="flex items-center justify-center min-h-screen bg-gray-50">
                 <div className="text-center">
                    <p className="text-gray-500 mb-4">Session expired or invalid.</p>
                    <Button onClick={() => navigate('/admin/login')}>Return to Login</Button>
                 </div>
            </div>
        );
    }

    const maskedPhone = user.phone ? user.phone.replace(/(\d{3})\d+(\d{2})/, '$1****$2') : "your phone";

    return (
        <div className="min-h-screen bg-gradient-to-br from-[#003D82] to-[#002855] flex items-center justify-center p-4">
            <motion.div 
                initial={{ opacity: 0, scale: 0.95 }}
                animate={{ opacity: 1, scale: 1 }}
                className="max-w-md w-full bg-white rounded-2xl shadow-2xl overflow-hidden"
            >
                <div className="bg-white p-8">
                    <div className="text-center mb-8">
                        <div className="mx-auto w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mb-4">
                            <LockKeyhole className="w-8 h-8 text-[#003D82]" />
                        </div>
                        <h2 className="text-2xl font-bold text-[#003D82]">Security Verification</h2>
                        <p className="text-gray-500 mt-2 text-sm">
                            Please enter the 6-digit code sent to <br/>
                            <span className="font-semibold text-gray-700">{maskedPhone}</span> via WhatsApp.
                        </p>
                    </div>

                    <form onSubmit={handleVerify} className="space-y-6">
                        <div className="space-y-2">
                            <Input
                                type="text"
                                maxLength="6"
                                placeholder="000000"
                                autoComplete="one-time-code"
                                inputMode="numeric"
                                className="text-center text-3xl tracking-[1em] h-16 font-bold border-gray-300 focus:border-[#D4AF37] focus:ring-[#D4AF37]/20 uppercase"
                                value={otp}
                                onChange={(e) => setOtp(e.target.value.replace(/[^0-9]/g, ''))}
                                disabled={isLoading || attempts <= 0}
                            />
                        </div>

                        <div className="flex justify-between items-center text-sm">
                            <span className={`${timeLeft < 60 ? 'text-red-500 font-bold' : 'text-gray-500'}`}>
                                Expires in: {formatTime(timeLeft)}
                            </span>
                            <span className={`${attempts < 3 ? 'text-red-500 font-bold' : 'text-gray-500'}`}>
                                Attempts left: {attempts}
                            </span>
                        </div>

                        {attempts <= 0 && (
                            <div className="bg-red-50 text-red-600 p-3 rounded-md flex items-center gap-2 text-sm">
                                <AlertTriangle className="w-4 h-4" />
                                <span>Max attempts reached. Please resend code.</span>
                            </div>
                        )}

                        <Button 
                            type="submit" 
                            className="w-full bg-[#D4AF37] hover:bg-[#b5952f] text-[#003D82] font-bold h-12 text-lg shadow-md"
                            disabled={isLoading || otp.length < 6 || attempts <= 0}
                        >
                            {isLoading ? <Loader2 className="w-5 h-5 animate-spin" /> : "Verify Identity"}
                        </Button>
                    </form>

                    <div className="mt-8 pt-6 border-t border-gray-100 flex flex-col items-center gap-4">
                        <Button 
                            variant="ghost" 
                            className="text-[#003D82] hover:bg-blue-50"
                            onClick={handleResend}
                            disabled={!canResend || isLoading}
                        >
                            <RefreshCw className={`w-4 h-4 mr-2 ${!canResend && 'animate-none'}`} />
                            {canResend ? "Resend Verification Code" : `Resend in ${resendCooldown}s`}
                        </Button>

                        <button 
                            onClick={logout}
                            className="text-gray-400 text-sm hover:text-gray-600 transition-colors"
                        >
                            Back to Login
                        </button>
                    </div>
                </div>
                
                {/* Progress Bar for Timer */}
                <div className="h-1 bg-gray-100 w-full">
                    <div 
                        className="h-full bg-[#D4AF37] transition-all duration-1000 ease-linear"
                        style={{ width: `${(timeLeft / 300) * 100}%` }}
                    />
                </div>
            </motion.div>
        </div>
    );
};

export default OTPVerificationScreen;