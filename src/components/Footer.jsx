import React, { useState, useEffect } from 'react';
import { Link } from 'react-router-dom';
import { Phone, Mail, Globe } from 'lucide-react';
import WhatsAppButton from '@/components/WhatsAppButton';
import BrandLogo from '@/components/BrandLogo';
import { getSystemSettings } from '@/services/settingsService';

function Footer() {
  const currentYear = new Date().getFullYear();
  const [settings, setSettings] = useState({
      developed_by: 'Alpha Bridge Technologies Ltd',
      copyright_text: 'All rights reserved',
      logo_url: null
  });

  useEffect(() => {
    const fetchSettings = async () => {
        try {
            const data = await getSystemSettings();
            if (data) {
                setSettings(prev => ({
                    ...prev,
                    developed_by: data.developed_by || prev.developed_by,
                    copyright_text: data.copyright_text || prev.copyright_text,
                    logo_url: data.logo_url
                }));
            }
        } catch (error) {
            console.warn("Footer: using default settings");
        }
    };
    fetchSettings();
  }, []);

  const quickLinks = [
    { name: 'Home', path: '/' },
    { name: 'About', path: '/about' },
    { name: 'Services', path: '/services' },
    { name: 'Projects', path: '/projects' },
    { name: 'Contact', path: '/contact' },
    { name: 'Shareholders Portal', path: '/shareholders' },
  ];

  // Default logo if not set — kept for settings merge only
  return (
    <footer className="bg-[#1a1a2e] text-white mt-auto">
      <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
        <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
          {/* Company Info */}
          <div>
            <div className="mb-4">
              <Link to="/" className="inline-block mb-2 bg-transparent">
                <BrandLogo
                  alt="Company logo"
                  className="h-[50px] w-auto object-contain hover:scale-105 transition-all duration-300"
                  variant="onDark"
                  src={settings.logo_url || undefined}
                />
              </Link>
              <div className="text-2xl font-bold">
                <span className="text-[#D4AF37]">Alpha Bridge</span>
                <span className="block text-lg">Technologies Ltd</span>
              </div>
            </div>
            <p className="text-gray-300 text-sm mb-4">
              Your Technology Bridge to Kigali. Professional IT, networking, security, and audio-visual solutions.
            </p>
          </div>

          {/* Quick Links */}
          <div>
            <h3 className="text-lg font-semibold text-[#D4AF37] mb-4">Quick Links</h3>
            <nav className="flex flex-col space-y-2">
              {quickLinks.map((link) => (
                <Link
                  key={link.path}
                  to={link.path}
                  className="text-gray-300 hover:text-[#D4AF37] transition-colors text-sm"
                >
                  {link.name}
                </Link>
              ))}
            </nav>
          </div>

          {/* Contact Info */}
          <div>
            <h3 className="text-lg font-semibold text-[#D4AF37] mb-4">Contact Us</h3>
            <div className="space-y-3">
              <div className="mb-4">
                 <WhatsAppButton 
                    text="Chat now" 
                    className="w-full sm:w-auto bg-[#25D366] hover:bg-[#1EBE57]"
                 />
              </div>
              <a
                href="tel:+250794006160"
                className="flex items-center space-x-3 text-gray-300 hover:text-[#D4AF37] transition-colors text-sm"
              >
                <Phone className="w-5 h-5 flex-shrink-0" />
                <span>+250 794 006 160</span>
              </a>
              <a
                href="mailto:info@alpha-bridge.net"
                className="flex items-center space-x-3 text-gray-300 hover:text-[#D4AF37] transition-colors text-sm"
              >
                <Mail className="w-5 h-5 flex-shrink-0" />
                <span>info@alpha-bridge.net</span>
              </a>
              <a
                href="https://www.alpha-bridge.net"
                target="_blank"
                rel="noopener noreferrer"
                className="flex items-center space-x-3 text-gray-300 hover:text-[#D4AF37] transition-colors text-sm"
              >
                <Globe className="w-5 h-5 flex-shrink-0" />
                <span>www.alpha-bridge.net</span>
              </a>
            </div>
          </div>
        </div>

        {/* Copyright */}
        <div className="mt-12 pt-8 border-t border-gray-700 text-center">
          <p className="text-gray-400 text-sm">
            © {currentYear} {settings.copyright_text}
          </p>
          <p className="text-gray-500 text-xs mt-2">
             Developed by <span className="text-gray-400 font-medium">{settings.developed_by}</span>
          </p>
          <p className="text-gray-600 text-[10px] mt-1">
             Kigali, Rwanda
          </p>
        </div>
      </div>
    </footer>
  );
}

export default Footer;