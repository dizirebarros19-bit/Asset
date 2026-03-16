<!DOCTYPE html>
<html lang="en" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>About AMS - Asset Management System</title>
    <script src="https://cdn.tailwindcss.com/3.4.17"></script>
    <script src="https://cdn.jsdelivr.net/npm/lucide@0.263.0/dist/umd/lucide.min.js"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        'custom-teal': '#3A7472',
                        'custom-teal-light': '#4A8A87',
                        'custom-teal-dark': '#2A5A58',
                        'agency-bg': '#EAF2F2',
                        'dark-section': '#1A1A1A',
                        'off-white': '#F8F9F9'
                    }
                }
            }
        }
    </script>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@300;400;500;700;800&family=Playfair+Display:wght@700;900&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'DM Sans', sans-serif; scroll-behavior: smooth; }
        .font-serif { font-family: 'Playfair Display', serif; }
        
        @keyframes fadeInUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-hero { animation: fadeInUp 1s cubic-bezier(0.16, 1, 0.3, 1) forwards; }
        
        .page-section { min-height: 100vh; display: flex; flex-direction: column; justify-content: center; padding: 5rem 0; }
        .snap-container { height: 100vh; overflow-y: auto; scroll-snap-type: y mandatory; }
        .snap-section { scroll-snap-align: start; min-height: 100vh; }
        
        ::-webkit-scrollbar { width: 6px; }
        ::-webkit-scrollbar-track { background: transparent; }
        ::-webkit-scrollbar-thumb { background: #3A7472; border-radius: 10px; }
    </style>
</head>
<body class="bg-off-white h-full overflow-hidden">

    <div class="snap-container">
        
        <section class="snap-section page-section px-6 relative overflow-hidden">
            <div class="absolute top-0 right-0 w-1/3 h-full bg-agency-bg/30 -z-10 translate-x-1/2 skew-x-12"></div>
            
            <div class="max-w-6xl mx-auto w-full text-center animate-hero">
                <div class="flex justify-center items-center gap-3 mb-10">
                    <div class="w-10 h-[1px] bg-custom-teal"></div>
                    <span class="uppercase tracking-[0.4em] text-xs font-black text-custom-teal">Established 2026</span>
                    <div class="w-10 h-[1px] bg-custom-teal"></div>
                </div>

                <img src="logo2.png" alt="AMS Logo" class="mx-auto h-32 md:h-40 w-auto mb-12 drop-shadow-xl hover:scale-105 transition-transform duration-500">
                
                <h1 class="text-6xl md:text-8xl font-serif font-black text-gray-900 leading-[1.1] mb-8">
                    Elevate Your <br> <span class="text-custom-teal">Asset Strategy</span>
                </h1>
                
                <p class="text-xl md:text-2xl text-gray-500 max-w-2xl mx-auto leading-relaxed font-light mb-12">
                    Empowering organizations with precision tracking, real-time inventory insights, and seamless warehouse operations.
                </p>

                <div class="flex flex-col items-center gap-10">
                    <a href="login.php" class="inline-flex items-center gap-3 bg-custom-teal text-white px-8 py-4 rounded-full font-bold uppercase tracking-widest text-xs hover:bg-custom-teal-dark transition-all shadow-lg hover:shadow-custom-teal/20">
                        <i data-lucide="arrow-left" class="w-4 h-4"></i>
                        Back to Login
                    </a>

                    <div class="flex flex-col items-center gap-4">
                        <div class="w-[1px] h-12 bg-gradient-to-b from-custom-teal to-transparent"></div>
                        <span class="text-xs font-bold uppercase tracking-[0.3em] text-gray-400">Scroll to Explore</span>
                    </div>
                </div>
            </div>
        </section>

        <section class="snap-section page-section bg-agency-bg px-6">
            <div class="max-w-6xl mx-auto w-full">
                <div class="text-center mb-16">
                    <div class="flex justify-center items-center gap-2 mb-4">
                        <div class="w-3 h-3 bg-red-600 rounded-full"></div>
                        <span class="uppercase tracking-[0.3em] text-xs font-bold text-red-600">Strategy Lead</span>
                    </div>
                    <h2 class="text-5xl md:text-7xl font-serif font-black text-gray-900">Project Manager</h2>
                </div>

                <div class="grid md:grid-cols-2 gap-12 items-center">
                    <div class="relative group rounded-[3rem] overflow-hidden shadow-2xl aspect-[4/5] bg-white">
                        <img src="thonie.jpg" class="w-full h-full object-cover transition-all duration-700">
                        <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-transparent to-transparent p-10 flex flex-col justify-end">
                            <h3 class="text-white text-4xl font-serif font-bold">Anthony Revil</h3>
                            <p class="text-red-500 font-bold tracking-widest uppercase text-sm mt-2">Visionary & Founder</p>
                        </div>
                    </div>
                    <div class="space-y-8">
                        <i data-lucide="quote" class="w-12 h-12 text-custom-teal/20"></i>
                        <h3 class="text-4xl md:text-5xl font-serif font-bold text-gray-800 leading-tight">
                            "The ultimate sophistication is simplicity."
                        </h3>
                        <p class="text-xl text-gray-600 leading-relaxed font-light">
                            believes that a system is only as powerful as it is understandable. He oversees the architectural integrity of AMS, ensuring that intricate asset logic is distilled into a seamless, intuitive experience for every user.
                        </p>
                        <div class="h-[1px] w-20 bg-custom-teal"></div>
                    </div>
                </div>
            </div>
        </section>

        <section class="snap-section page-section bg-dark-section px-6">
            <div class="max-w-7xl mx-auto w-full">
                <div class="mb-16 text-center md:text-left">
                    <span class="text-custom-teal-light font-bold uppercase tracking-[0.4em] text-xs">Innovation Team</span>
                    <h2 class="text-5xl md:text-7xl font-serif text-white mt-4">The Experts</h2>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 lg:grid-cols-5 gap-4">
                    <div class="group relative overflow-hidden rounded-2xl aspect-[3/4] cursor-pointer">
                        <img src="nicole.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        <div class="absolute inset-0 bg-custom-teal/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-xl">Nicole Laureno</h4>
                            <p class="text-white/80 text-xs uppercase tracking-widest mt-1">Lead Developer</p>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl aspect-[3/4] cursor-pointer">
                        <img src="viviene.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        <div class="absolute inset-0 bg-custom-teal/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-xl">Viviene Nacen</h4>
                            <p class="text-white/80 text-xs uppercase tracking-widest mt-1">UI/UX Designer</p>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl aspect-[3/4] cursor-pointer">
                        <img src="dezire.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        <div class="absolute inset-0 bg-custom-teal/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-xl">Dizire Barros</h4>
                            <p class="text-white/80 text-xs uppercase tracking-widest mt-1">Programmer</p>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl aspect-[3/4] cursor-pointer">
                        <img src="akira.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        <div class="absolute inset-0 bg-custom-teal/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-xl">Akira Rio</h4>
                            <p class="text-white/80 text-xs uppercase tracking-widest mt-1">Documentation</p>
                        </div>
                    </div>
                    <div class="group relative overflow-hidden rounded-2xl aspect-[3/4] cursor-pointer">
                      <img src="akira.jpg" class="w-full h-full object-cover transition-transform duration-700 group-hover:scale-110">
                        <div class="absolute inset-0 bg-custom-teal/80 opacity-0 group-hover:opacity-100 transition-opacity duration-500 flex flex-col justify-end p-6">
                            <h4 class="text-white font-bold text-xl">Akira Rio</h4>
                            <p class="text-white/80 text-xs uppercase tracking-widest mt-1">Documentation</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>

        <section class="snap-section page-section px-6 bg-off-white">
            <div class="max-w-6xl mx-auto w-full">
                <div class="grid lg:grid-cols-2 bg-white rounded-[3rem] shadow-2xl overflow-hidden">
                    <div class="p-16 flex flex-col justify-center">
                        <h2 class="text-5xl font-serif font-black text-gray-900 mb-8">Visit Us</h2>
                        <div class="space-y-8">
                            <div>
                                <h4 class="text-custom-teal font-black uppercase tracking-widest text-xs mb-2">Our Location</h4>
                                <p class="text-xl text-gray-600">Navotas City, Metro Manila,<br>Philippines 1485</p>
                            </div>
                            <div>
                                <h4 class="text-custom-teal font-black uppercase tracking-widest text-xs mb-2">Direct Inquiries</h4>
                                <p class="text-xl text-gray-600 font-bold">support@ams-system.com</p>
                            </div>
                            
                            <div class="pt-4">
                                <a href="login.php" class="inline-flex items-center gap-2 text-custom-teal font-bold hover:text-custom-teal-dark group transition-colors">
                                    <span class="uppercase tracking-widest text-xs">Ready to return? Login here</span>
                                    <i data-lucide="log-in" class="w-4 h-4 group-hover:translate-x-1 transition-transform"></i>
                                </a>
                            </div>
                        </div>
                    </div>
                    <div class="h-96 lg:h-auto bg-gray-100">
                         <iframe class="w-full h-full border-0" src="http://googleusercontent.com/maps.google.com/7" allowfullscreen="" loading="lazy"></iframe>
                    </div>
                </div>
                <footer class="mt-12 text-center text-gray-400 text-xs tracking-widest uppercase">
                    © 2026 Asset Management System — Precision at Scale.
                </footer>
            </div>
        </section>

    </div>

    <script>
        lucide.createIcons();
    </script>
</body>
</html>