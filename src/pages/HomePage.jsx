import React, { useState, useEffect } from 'react';
import { Helmet } from 'react-helmet';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import WhatsAppButton from '@/components/WhatsAppButton';
import { getAllEvents } from '@/services/eventService';
import { Network, Shield, Mic, Monitor, CheckCircle2, Zap, TrendingUp, Mail, Building2, Church, Calendar, School, Heart, Home, Cable, ArrowRight } from 'lucide-react';
import { motion } from 'framer-motion';
import BrandLogo from '@/components/BrandLogo';
function HomePage() {
  const navigate = useNavigate();
  const [upcomingEvents, setUpcomingEvents] = useState([]);
  useEffect(() => {
    let isMounted = true;
    const initData = async () => {
      try {
        const events = await getAllEvents();
        if (isMounted) {
          setUpcomingEvents(Array.isArray(events) ? events.slice(0, 3) : []);
        }
      } catch (error) {
        console.error("HomePage: Failed to load events", error);
        if (isMounted) setUpcomingEvents([]);
      }
    };
    initData();
    return () => {
      isMounted = false;
    };
  }, []);
  const services = [{
    icon: <Network className="w-12 h-12" />,
    title: 'IT Consultancy',
    description: 'Enterprise-grade IT solutions and infrastructure planning'
  }, {
    icon: <Network className="w-12 h-12" />,
    title: 'Networks',
    description: 'Professional networking design, deployment, and management'
  }, {
    icon: <Shield className="w-12 h-12" />,
    title: 'CCTV & Security',
    description: 'Advanced surveillance and security systems'
  }, {
    icon: <Mic className="w-12 h-12" />,
    title: 'Sound & Audio',
    description: 'Professional audio engineering for events and venues'
  }, {
    icon: <Monitor className="w-12 h-12" />,
    title: 'Screens & Lighting',
    description: 'LED screens and professional lighting solutions'
  }, {
    icon: <Cable className="w-12 h-12" />,
    title: 'Fiber Optics',
    description: 'High-speed fiber connectivity and splicing services'
  }];
  const whyChooseUs = [{
    icon: <CheckCircle2 className="w-10 h-10" />,
    title: 'Engineering Standards',
    description: 'Built with precision and best practices'
  }, {
    icon: <Shield className="w-10 h-10" />,
    title: 'Reliability',
    description: 'Dependable systems you can trust'
  }, {
    icon: <Zap className="w-10 h-10" />,
    title: 'Fast Support',
    description: 'Quick response times and expert assistance'
  }, {
    icon: <TrendingUp className="w-10 h-10" />,
    title: 'Scalable Solutions',
    description: 'Systems that grow with your needs'
  }];
  const industries = [{
    icon: <Building2 className="w-10 h-10" />,
    name: 'Companies'
  }, {
    icon: <Church className="w-10 h-10" />,
    name: 'Churches'
  }, {
    icon: <Calendar className="w-10 h-10" />,
    name: 'Events'
  }, {
    icon: <School className="w-10 h-10" />,
    name: 'Schools'
  }, {
    icon: <Heart className="w-10 h-10" />,
    name: 'NGOs'
  }, {
    icon: <Home className="w-10 h-10" />,
    name: 'Homes'
  }];
  const testimonials = [{
    name: 'Client A',
    role: 'CEO, Tech Company',
    content: 'Alpha Bridge delivered exceptional networking solutions for our office. Professional and reliable.'
  }, {
    name: 'Client B',
    role: 'Event Organizer',
    content: 'Their sound and lighting setup made our event unforgettable. Highly recommended!'
  }, {
    name: 'Client C',
    role: 'School Administrator',
    content: 'The CCTV system they installed has greatly improved our campus security.'
  }];

  return <>
      <Helmet>
        <title>Alpha Bridge Technologies Ltd | IT Consultancy & AV Solutions in Kigali</title>
        <meta name="description" content="Alpha Bridge Technologies Ltd — IT consultancy, networks, CCTV security, and professional sound/screen/lighting solutions in Kigali, Rwanda. Chat with us on WhatsApp for a quote." />
      </Helmet>

      {/* Hero Section */}
      <section className="relative min-h-screen flex flex-col items-center justify-center overflow-hidden py-20 md:py-0">
        <div className="absolute inset-0 bg-cover bg-center bg-no-repeat" style={{
        backgroundImage: 'url(https://horizons-cdn.hostinger.com/81ef3422-3855-479e-bfe8-28a4ceb0df39/dd5d2c583a8b091d7529cd8c4e5ff3ea.png)',
        backgroundPosition: 'center',
        backgroundSize: 'cover'
      }}>
          <div className="absolute inset-0 bg-black/40"></div>
          <div className="absolute inset-0 bg-gradient-to-t from-[#003D82]/90 via-[#003D82]/30 to-transparent"></div>
        </div>
        
        <div className="relative z-10 max-w-6xl mx-auto px-4 sm:px-6 lg:px-8 text-center w-full mt-20 md:mt-0">
          <motion.div initial={{
          opacity: 0,
          y: 30
        }} animate={{
          opacity: 1,
          y: 0
        }} transition={{
          duration: 0.8
        }} className="mb-8 flex flex-col items-center">
             <BrandLogo
               alt="Alpha Bridge"
               className="h-24 md:h-32 w-auto object-contain mb-6"
               variant="onDark"
             />

             <h1 className="text-4xl md:text-6xl lg:text-7xl font-bold text-white mb-4 drop-shadow-2xl tracking-tight">
               Your Technology Bridge to <span className="text-[#D4AF37]">Kigali</span>
             </h1>
             <p className="text-xl md:text-2xl text-white/90 font-light max-w-3xl mx-auto drop-shadow-md">
                Professional IT Consultancy, Enterprise Networking, and Audio-Visual Production, Cloud, AI and Cyber
             </p>
          </motion.div>
          
          <motion.div initial={{
          opacity: 0,
          y: 30
        }} animate={{
          opacity: 1,
          y: 0
        }} transition={{
          duration: 0.8,
          delay: 0.4
        }} className="w-full flex flex-col sm:flex-row items-center justify-center gap-6">
            
            <Link to="/contact">
                <Button className="bg-[#D4AF37] hover:bg-[#b5952f] text-[#003D82] h-14 px-8 text-lg font-bold shadow-[0_0_15px_rgba(212,175,55,0.4)] w-full sm:w-auto rounded-full hover:scale-105 transition-transform">
                  Get a Free Quote <ArrowRight className="ml-2 w-5 h-5" />
                </Button>
            </Link>

            <WhatsAppButton className="h-14 px-8 text-lg font-bold rounded-full shadow-xl hover:shadow-2xl" />

          </motion.div>
        </div>
      </section>

      {/* Upcoming Events Section (Dynamic) */}
      {upcomingEvents && upcomingEvents.length > 0 && <section className="py-16 bg-gray-50">
           <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
             <div className="text-center mb-12">
               <h2 className="text-4xl font-bold text-[#003D82] mb-4">Upcoming Events</h2>
               <p className="text-xl text-gray-600">Join us at our next gathering</p>
             </div>
             
             <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
                {upcomingEvents.map(evt => <motion.div key={evt?.id || Math.random().toString()} initial={{
            opacity: 0,
            y: 20
          }} whileInView={{
            opacity: 1,
            y: 0
          }} viewport={{
            once: true
          }} className="bg-white rounded-xl shadow-lg overflow-hidden border border-gray-100 hover:shadow-xl transition-all">
                      <div className="h-48 relative bg-gray-200">
                        <img src={evt?.image_url || 'https://via.placeholder.com/400x200'} alt={evt?.title || 'Event'} className="w-full h-full object-cover" />
                        {evt?.date && <div className="absolute top-2 right-2 bg-blue-600 text-white text-xs font-bold px-3 py-1 rounded-full uppercase">
                               {new Date(evt.date).toLocaleDateString(undefined, {
                  month: 'short',
                  day: 'numeric'
                })}
                            </div>}
                      </div>
                      <div className="p-6">
                         <h3 className="text-xl font-bold text-[#003D82] mb-2">{evt?.title || 'Upcoming Event'}</h3>
                         {evt?.date && <div className="text-sm text-gray-500 mb-4 flex items-center">
                                <Calendar className="w-4 h-4 mr-2" />
                                {new Date(evt.date).toLocaleDateString(undefined, {
                  weekday: 'long',
                  year: 'numeric',
                  month: 'long',
                  day: 'numeric'
                })}
                             </div>}
                         <p className="text-gray-600 line-clamp-2 mb-4">{evt?.description || 'Details coming soon.'}</p>

                         <Link to="/events">
                            <Button className="w-full bg-[#D4AF37] text-[#003D82] font-bold hover:bg-[#c9a227]">
                                View Details
                            </Button>
                         </Link>
                      </div>
                   </motion.div>)}
             </div>
           </div>
        </section>}

      {/* Services Overview Section */}
      <section className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-[#003D82] mb-4">Our Services</h2>
            <p className="text-xl text-gray-600">Comprehensive technology solutions for your needs</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-8">
            {services.map((service, index) => <motion.div key={index} initial={{
            opacity: 0,
            y: 20
          }} whileInView={{
            opacity: 1,
            y: 0
          }} viewport={{
            once: true
          }} transition={{
            duration: 0.5,
            delay: index * 0.1
          }} className="bg-white rounded-xl shadow-lg hover:shadow-2xl transition-all duration-300 p-8 border border-gray-100">
                <div className="text-[#0066CC] mb-4">{service.icon}</div>
                <h3 className="text-2xl font-semibold text-[#003D82] mb-3">{service.title}</h3>
                <p className="text-gray-700">{service.description}</p>
              </motion.div>)}
          </div>
        </div>
      </section>

      {/* Why Alpha Bridge Section */}
      <section className="py-16 bg-[#003D82]">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-white mb-4">Why Alpha Bridge?</h2>
            <p className="text-xl text-gray-300">Excellence in every solution we deliver</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
            {whyChooseUs.map((feature, index) => <motion.div key={index} initial={{
            opacity: 0,
            scale: 0.9
          }} whileInView={{
            opacity: 1,
            scale: 1
          }} viewport={{
            once: true
          }} transition={{
            duration: 0.5,
            delay: index * 0.1
          }} className="bg-white/10 backdrop-blur-md rounded-xl p-6 text-center hover:bg-white/20 transition-all duration-300 border border-white/5">
                <div className="text-[#D4AF37] mb-4 flex justify-center">{feature.icon}</div>
                <h3 className="text-xl font-semibold text-white mb-2">{feature.title}</h3>
                <p className="text-gray-300 text-sm">{feature.description}</p>
              </motion.div>)}
          </div>
        </div>
      </section>

      {/* Industries Served Section */}
      <section className="py-16 bg-white">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-[#003D82] mb-4">Industries We Serve</h2>
            <p className="text-xl text-gray-600">Trusted by diverse organizations across Africa and the World</p>
          </div>
          <div className="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-6">
            {industries.map((industry, index) => <motion.div key={index} initial={{
            opacity: 0,
            y: 20
          }} whileInView={{
            opacity: 1,
            y: 0
          }} viewport={{
            once: true
          }} transition={{
            duration: 0.5,
            delay: index * 0.1
          }} className="bg-white rounded-xl shadow-md p-6 text-center border border-gray-100 hover:shadow-lg transition-shadow">
                <div className="text-[#0066CC] mb-3 flex justify-center">{industry.icon}</div>
                <h3 className="text-lg font-semibold text-[#003D82]">{industry.name}</h3>
              </motion.div>)}
          </div>
        </div>
      </section>

      {/* Testimonials Section */}
      <section className="py-16 bg-gray-50">
        <div className="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
          <div className="text-center mb-12">
            <h2 className="text-4xl font-bold text-[#003D82] mb-4">What Our Clients Say</h2>
            <p className="text-xl text-gray-600">Trusted by businesses and organizations across Kigali</p>
          </div>
          <div className="grid grid-cols-1 md:grid-cols-3 gap-8">
            {testimonials.map((testimonial, index) => <motion.div key={index} initial={{
            opacity: 0,
            y: 20
          }} whileInView={{
            opacity: 1,
            y: 0
          }} viewport={{
            once: true
          }} transition={{
            duration: 0.5,
            delay: index * 0.1
          }} className="bg-white rounded-xl shadow-md p-8 border border-gray-200">
                <p className="text-gray-700 italic mb-6">"{testimonial.content}"</p>
                <div>
                  <p className="font-semibold text-[#003D82]">{testimonial.name}</p>
                  <p className="text-sm text-gray-500">{testimonial.role}</p>
                </div>
              </motion.div>)}
          </div>
        </div>
      </section>

      {/* CTA Banner */}
      <section className="py-16 bg-gradient-to-r from-[#003D82] via-[#0066CC] to-[#003D82]">
        <div className="max-w-4xl mx-auto px-4 sm:px-6 lg:px-8 text-center">
          <h2 className="text-4xl font-bold text-white mb-6">Ready to Get Started?</h2>
          <p className="text-xl text-gray-200 mb-8">
            Contact us today for a consultation and let us bridge your technology needs.
          </p>
          <div className="flex flex-col sm:flex-row gap-4 justify-center">
            <WhatsAppButton className="px-8 py-6 text-lg rounded-lg shadow-xl hover:shadow-2xl" />
            
            <a href="mailto:info@alpha-bridge.net">
              <Button className="bg-white text-[#003D82] hover:bg-gray-100 px-8 py-6 text-lg rounded-lg shadow-xl hover:shadow-2xl font-semibold">
                <Mail className="w-5 h-5 mr-2" />
                Email Us
              </Button>
            </a>
          </div>
        </div>
      </section>
    </>;
}
export default HomePage;