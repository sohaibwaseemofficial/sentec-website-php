/* ============================================
   PROXION 2026 — SENTEC
   script.js
   - WebGL Shader Background
   - Custom Cursor
   - Navbar Scroll Behavior
   - 3D Scroll Card Animation
   - Scroll Reveal Animations
   - Hero Particles
   - Mobile Nav
   ============================================ */

document.addEventListener('DOMContentLoaded', () => {

  // ============================================
  // 1. WEBGL SHADER BACKGROUND
  // ============================================
  (function initWebGL() {
    const canvas = document.getElementById('bg-canvas');
    if (!canvas) return;

    const gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
    if (!gl) {
      canvas.style.background = 'linear-gradient(135deg, #0d0c2b 0%, #1a0f3a 50%, #0d0c2b 100%)';
      return;
    }

    // Vertex Shader
    const vertSrc = `
      attribute vec2 a_position;
      void main() {
        gl_Position = vec4(a_position, 0.0, 1.0);
      }
    `;

    // Fragment Shader — plasma grid with warp, color scheme matching PROXION
    const fragSrc = `
      precision mediump float;

      uniform vec2 iResolution;
      uniform float iTime;

      #define PI 3.14159265359
      #define TAU 6.28318530718

      // Hash function
      float hash(vec2 p) {
        p = fract(p * vec2(234.34, 435.345));
        p += dot(p, p + 34.23);
        return fract(p.x * p.y);
      }

      // Smooth noise
      float noise(vec2 p) {
        vec2 i = floor(p);
        vec2 f = fract(p);
        f = f * f * (3.0 - 2.0 * f);
        float a = hash(i);
        float b = hash(i + vec2(1.0, 0.0));
        float c = hash(i + vec2(0.0, 1.0));
        float d = hash(i + vec2(1.0, 1.0));
        return mix(mix(a, b, f.x), mix(c, d, f.x), f.y);
      }

      // Fractal Brownian Motion
      float fbm(vec2 p) {
        float val = 0.0;
        float amp = 0.5;
        float freq = 1.0;
        for(int i = 0; i < 5; i++) {
          val += amp * noise(p * freq);
          freq *= 2.1;
          amp *= 0.48;
        }
        return val;
      }

      // Grid lines
      float gridLines(vec2 uv, float scale, float thickness) {
        vec2 g = fract(uv * scale);
        float lx = smoothstep(thickness, 0.0, min(g.x, 1.0 - g.x));
        float ly = smoothstep(thickness, 0.0, min(g.y, 1.0 - g.y));
        return max(lx, ly);
      }

      // Plasma wave
      float plasma(vec2 uv, float t) {
        float v = 0.0;
        v += sin(uv.x * 4.0 + t * 0.8);
        v += sin(uv.y * 3.0 + t * 0.6);
        v += sin((uv.x + uv.y) * 3.5 + t * 0.7);
        v += sin(length(uv - vec2(0.5)) * 6.0 - t * 1.2);
        return v * 0.25 + 0.5;
      }

      // Circle highlight
      float circle(vec2 uv, vec2 center, float radius, float softness) {
        float d = length(uv - center);
        return 1.0 - smoothstep(radius - softness, radius + softness, d);
      }

      void main() {
        vec2 fragCoord = gl_FragCoord.xy;
        vec2 uv = fragCoord / iResolution.xy;
        vec2 uvc = uv - 0.5;
        uvc.x *= iResolution.x / iResolution.y;

        float t = iTime * 0.25;

        // Warp UV with fbm
        vec2 warpedUV = uvc;
        warpedUV.x += fbm(uvc * 2.0 + vec2(t * 0.3, t * 0.2)) * 0.3;
        warpedUV.y += fbm(uvc * 2.0 + vec2(-t * 0.2, t * 0.35)) * 0.3;

        // Base background gradient — deep navy #0d0c2b
        vec3 bgColor = vec3(0.051, 0.047, 0.169);

        // Purple zone #6e46a3 = (0.431, 0.275, 0.639)
        vec3 purpleColor = vec3(0.431, 0.275, 0.639);

        // Orange accent #ed8507 = (0.929, 0.522, 0.027)
        vec3 orangeColor = vec3(0.929, 0.522, 0.027);

        // Plasma overlay — subtle
        float p1 = plasma(warpedUV * 1.5, t);
        float p2 = plasma(warpedUV * 2.0 + vec2(1.7, 2.3), t * 0.8);
        float plasmaVal = mix(p1, p2, 0.5);

        // Grid
        vec2 gridUV = uvc * 8.0;
        gridUV.x += t * 0.4;
        gridUV.y += t * 0.25;
        float grid1 = gridLines(gridUV, 1.0, 0.04) * 0.3;
        float grid2 = gridLines(uvc * 5.0, 1.0, 0.025) * 0.12;

        // Vignette
        float vignette = 1.0 - smoothstep(0.35, 1.1, length(uvc));

        // Circular glows
        float glow1 = circle(uvc, vec2(-0.4 + sin(t * 0.5) * 0.1, -0.2 + cos(t * 0.4) * 0.1), 0.5, 0.4);
        float glow2 = circle(uvc, vec2(0.5 + cos(t * 0.3) * 0.1, 0.3 + sin(t * 0.6) * 0.1), 0.4, 0.35);
        float glow3 = circle(uvc, vec2(0.0 + sin(t * 0.7) * 0.2, 0.4 + cos(t * 0.5) * 0.1), 0.3, 0.3);

        // Build color
        vec3 col = bgColor;

        // Purple cloud
        col = mix(col, purpleColor, glow1 * 0.18 * plasmaVal);
        col = mix(col, purpleColor * 0.7, glow2 * 0.12 * (1.0 - plasmaVal));
        col = mix(col, purpleColor * 0.5, glow3 * 0.1);

        // Orange spark — very subtle
        float sparks = plasma(warpedUV * 3.0, t * 1.5);
        col = mix(col, orangeColor, sparks * 0.025 * glow1);

        // Grid lines — subtle purple/blue tint
        col += purpleColor * grid1 * 0.4;
        col += vec3(0.3, 0.5, 1.0) * grid2;

        // Plasma color tint
        col += purpleColor * plasmaVal * 0.04;

        // Vignette
        col *= vignette;

        // Clamp
        col = clamp(col, 0.0, 1.0);

        // Gamma correction
        col = pow(col, vec3(0.85));

        gl_FragColor = vec4(col, 1.0);
      }
    `;

    function compileShader(gl, type, src) {
      const shader = gl.createShader(type);
      gl.shaderSource(shader, src);
      gl.compileShader(shader);
      if (!gl.getShaderParameter(shader, gl.COMPILE_STATUS)) {
        console.error('Shader compile error:', gl.getShaderInfoLog(shader));
        gl.deleteShader(shader);
        return null;
      }
      return shader;
    }

    const vert = compileShader(gl, gl.VERTEX_SHADER, vertSrc);
    const frag = compileShader(gl, gl.FRAGMENT_SHADER, fragSrc);
    if (!vert || !frag) return;

    const program = gl.createProgram();
    gl.attachShader(program, vert);
    gl.attachShader(program, frag);
    gl.linkProgram(program);

    if (!gl.getProgramParameter(program, gl.LINK_STATUS)) {
      console.error('Program link error:', gl.getProgramInfoLog(program));
      return;
    }

    gl.useProgram(program);

    // Full-screen quad
    const quadBuf = gl.createBuffer();
    gl.bindBuffer(gl.ARRAY_BUFFER, quadBuf);
    gl.bufferData(gl.ARRAY_BUFFER, new Float32Array([
      -1,-1,  1,-1,  -1,1,
       1,-1,  1, 1,  -1,1
    ]), gl.STATIC_DRAW);

    const aPos = gl.getAttribLocation(program, 'a_position');
    gl.enableVertexAttribArray(aPos);
    gl.vertexAttribPointer(aPos, 2, gl.FLOAT, false, 0, 0);

    const uRes  = gl.getUniformLocation(program, 'iResolution');
    const uTime = gl.getUniformLocation(program, 'iTime');

    function resize() {
      canvas.width  = window.innerWidth;
      canvas.height = window.innerHeight;
      gl.viewport(0, 0, canvas.width, canvas.height);
    }
    resize();
    window.addEventListener('resize', resize);

    let startTime = performance.now();
    let animId;

    function render() {
      const elapsed = (performance.now() - startTime) / 1000;
      gl.uniform2f(uRes, canvas.width, canvas.height);
      gl.uniform1f(uTime, elapsed);
      gl.drawArrays(gl.TRIANGLES, 0, 6);
      animId = requestAnimationFrame(render);
    }

    // Pause when tab is hidden
    document.addEventListener('visibilitychange', () => {
      if (document.hidden) {
        cancelAnimationFrame(animId);
      } else {
        startTime = performance.now() - (startTime ? 0 : 0);
        render();
      }
    });

    render();

    // Handle WebGL context loss
    canvas.addEventListener('webglcontextlost', (e) => {
      e.preventDefault();
      cancelAnimationFrame(animId);
    });
    canvas.addEventListener('webglcontextrestored', () => {
      startTime = performance.now();
      render();
    });

  })();


  // ============================================
  // 2. CUSTOM CURSOR
  // ============================================
  (function initCursor() {
    const cursor = document.getElementById('cursor');
    const trail  = document.getElementById('cursor-trail');
    if (!cursor || !trail) return;

    let mouseX = 0, mouseY = 0;
    let trailX = 0, trailY = 0;

    document.addEventListener('mousemove', (e) => {
      mouseX = e.clientX;
      mouseY = e.clientY;
      cursor.style.left = mouseX + 'px';
      cursor.style.top  = mouseY + 'px';
    });

    function animTrail() {
      trailX += (mouseX - trailX) * 0.12;
      trailY += (mouseY - trailY) * 0.12;
      trail.style.left = trailX + 'px';
      trail.style.top  = trailY + 'px';
      requestAnimationFrame(animTrail);
    }
    animTrail();

    // Hover effect
    document.querySelectorAll('a, button, .module-card, .btn-rulebook, .modal-btn-reg').forEach(el => {
      el.addEventListener('mouseenter', () => {
        cursor.style.width  = '20px';
        cursor.style.height = '20px';
        trail.style.width   = '56px';
        trail.style.height  = '56px';
        trail.style.borderColor = 'rgba(237,133,7,0.6)';
        // Dynamically change trail color based on which button you hover
        if (el.classList.contains('modal-btn-reg')) {
            trail.style.borderColor = 'rgba(237,133,7,0.6)'; // Orange pulse
        } else {
            trail.style.borderColor = 'rgba(155,111,212,0.6)'; // Purple pulse
        }
      });
      el.addEventListener('mouseleave', () => {
        cursor.style.width  = '12px';
        cursor.style.height = '12px';
        trail.style.width   = '36px';
        trail.style.height  = '36px';
        trail.style.borderColor = 'rgba(110,70,163,0.6)';
      });
    });
  })();


  // ============================================
  // 3. NAVBAR SCROLL BEHAVIOR
  // ============================================
  (function initNavbar() {
    const navbar = document.getElementById('navbar');
    if (!navbar) return;
    const onScroll = () => {
      if (window.scrollY > 60) {
        navbar.classList.add('scrolled');
      } else {
        navbar.classList.remove('scrolled');
      }
    };
    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();

    // Mobile menu
    const hamburger = document.getElementById('hamburger');
    const mobileMenu = document.getElementById('mobile-menu');
    if (hamburger && mobileMenu) {
      hamburger.addEventListener('click', () => {
        mobileMenu.classList.toggle('open');
      });
      mobileMenu.querySelectorAll('a').forEach(a => {
        a.addEventListener('click', () => mobileMenu.classList.remove('open'));
      });
    }
  })();


  // ============================================
  // 4. HERO PARTICLES
  // ============================================
  (function initParticles() {
    const container = document.getElementById('hero-particles');
    if (!container) return;

    const COUNT = 35;
    const colors = ['rgba(110,70,163,0.7)', 'rgba(237,133,7,0.5)', 'rgba(155,111,212,0.6)', 'rgba(255,255,255,0.3)'];

    for (let i = 0; i < COUNT; i++) {
      const p = document.createElement('div');
      p.className = 'particle';

      const size = Math.random() * 4 + 1;
      const x = Math.random() * 100;
      const delay = Math.random() * 12;
      const duration = 8 + Math.random() * 14;
      const color = colors[Math.floor(Math.random() * colors.length)];

      p.style.cssText = `
        left: ${x}%;
        width: ${size}px;
        height: ${size}px;
        background: ${color};
        animation-duration: ${duration}s;
        animation-delay: -${delay}s;
        box-shadow: 0 0 ${size * 3}px ${color};
      `;

      container.appendChild(p);
    }
  })();


  // ============================================
  // 5. SCROLL REVEAL (IntersectionObserver)
  // ============================================
  (function initScrollReveal() {
    const revealEls = document.querySelectorAll('.reveal-up, .reveal-card');
    if (!revealEls.length) return;

    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('visible');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.1,
      rootMargin: '0px 0px -40px 0px'
    });

    revealEls.forEach(el => observer.observe(el));
  })();


  // ============================================
  // 6. 3D SCROLL CARD ANIMATION
  // ============================================
 (function init3DScrollCard() {
      const container = document.getElementById('scroll3d-container');
      const card      = document.getElementById('card-3d');
      const allTitles = document.querySelectorAll('.reveal-title');

      if (!container || !card) return;

      const isMobile = () => window.innerWidth <= 768;
      const lerp = (a, b, t) => a + (b - a) * t;

      function updateElements() {
          const rect = container.getBoundingClientRect();
          const viewH = window.innerHeight;

          // Progress logic: 0 when top is at bottom of screen, 1 when top is at top of screen
          let progress = (viewH - rect.top) / (rect.height);
          let p = Math.max(0, Math.min(1, progress));
          p = Math.pow(p, 1.2);

          // 1. 3D CARD ANIMATION
          // rotateX: 20 -> 0 | translateY: 60 -> 0
          const rotateX = lerp(20, 0, p);
          const translateY = lerp(100, 0, p);
          const scale = lerp(0.9, 1.0, p);
          
          card.style.transform = `rotateX(${rotateX}deg) scale(${scale}) translateY(${translateY}px)`;

          // 2. ALL TITLES PARALLAX
          allTitles.forEach(title => {
              const tRect = title.getBoundingClientRect();
              // Unique progress for each heading based on its own position
              let tProgress = Math.max(0, Math.min(1, (viewH - tRect.top) / (viewH * 0.7)));
              
              // This creates the parallax "float"
              title.style.transform = `translateY(${lerp(40, 0, tProgress)}px)`;
              title.style.opacity = tProgress; 
          });
      }

      window.addEventListener('scroll', () => requestAnimationFrame(updateElements), { passive: true });
      updateElements();
  })();


  // ============================================
  // 7. SMOOTH SECTION INDICATOR / ACTIVE NAV
  // ============================================
  (function initActiveNav() {
    const sections = document.querySelectorAll('section[id]');
    const navLinks = document.querySelectorAll('.nav-links a');

    function updateActive() {
      let current = '';
      sections.forEach(section => {
        const top = section.offsetTop - 120;
        if (window.scrollY >= top) {
          current = section.id;
        }
      });

      navLinks.forEach(link => {
        link.style.color = '';
        if (link.getAttribute('href') === '#' + current) {
          link.style.color = '#ffffff';
        }
      });
    }

    window.addEventListener('scroll', updateActive, { passive: true });
    updateActive();
  })();


  // ============================================
  // 8. MODULE CARD TILT EFFECT
  // ============================================
  (function initTilt() {
    const cards = document.querySelectorAll('.module-card, .exhibit-card, .esport-card, .general-card');
    cards.forEach(card => {
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const cx = rect.left + rect.width / 2;
        const cy = rect.top + rect.height / 2;
        const dx = (e.clientX - cx) / (rect.width / 2);
        const dy = (e.clientY - cy) / (rect.height / 2);
        const tiltX = dy * -5;
        const tiltY = dx * 5;
        card.style.transform = `translateY(-4px) rotateX(${tiltX}deg) rotateY(${tiltY}deg) scale(1.01)`;
        card.style.transition = 'transform 0.1s linear';
      });
      card.addEventListener('mouseleave', () => {
        card.style.transform = '';
        card.style.transition = 'transform 0.4s ease, box-shadow 0.4s ease, border-color 0.3s, background 0.3s';
      });
    });
  })();


  // ============================================
  // 9. ESPORT GLOW FOLLOW MOUSE
  // ============================================
  (function initEsportGlow() {
    document.querySelectorAll('.esport-card').forEach(card => {
      const glow = card.querySelector('.esport-glow');
      if (!glow) return;
      card.addEventListener('mousemove', (e) => {
        const rect = card.getBoundingClientRect();
        const x = ((e.clientX - rect.left) / rect.width) * 100;
        const y = ((e.clientY - rect.top) / rect.height) * 100;
        glow.style.background = `radial-gradient(circle at ${x}% ${y}%, rgba(110,70,163,0.35) 0%, transparent 60%)`;
      });
    });
  })();


  // ============================================
  // 10. GLITCH TEXT RE-TRIGGER ON HOVER
  // ============================================
  (function initGlitchHover() {
    const glitch = document.querySelector('.title-glitch');
    if (!glitch) return;
    glitch.addEventListener('mouseenter', () => {
      glitch.style.animation = 'none';
      glitch.offsetHeight; // reflow
      glitch.style.animation = '';
    });
  })();

});
document.addEventListener('mousemove', (e) => {
    const cards = document.querySelectorAll('.module-card');
    cards.forEach(card => {
        const rect = card.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const y = e.clientY - rect.top;
        
        card.style.setProperty('--mouse-x', `${x}px`);
        card.style.setProperty('--mouse-y', `${y}px`);
    });
});