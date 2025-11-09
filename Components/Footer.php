<!-- Footer -->
<footer class="footer">
    <div class="footer-wave"></div>
    
    <div class="footer-content">
        <div class="footer-grid">
            <!-- Brand Column -->
            <div class="footer-brand">
                <a href="/fashionhub/Customer/CustomerDashboard.php" class="footer-logo">
                    <i class="fas fa-shopping-bag"></i>
                    <span>FashionHub</span>
                </a>
                <p class="footer-description">
                    Your premier destination for the latest fashion trends. We bring you quality, style, and affordability in every piece. Shop with confidence and elevate your wardrobe today!
                </p>
                
                <div>
                    <h4 style="font-size: 16px; margin-bottom: 15px; color: white; font-weight: 700;">Follow Us</h4>
                    <div class="social-links">
                        <a href="https://facebook.com" target="_blank" class="social-link facebook" title="Facebook">
                            <i class="fab fa-facebook-f"></i>
                        </a>
                        <a href="https://instagram.com" target="_blank" class="social-link instagram" title="Instagram">
                            <i class="fab fa-instagram"></i>
                        </a>
                        <a href="https://twitter.com" target="_blank" class="social-link twitter" title="Twitter">
                            <i class="fab fa-twitter"></i>
                        </a>
                        <a href="https://youtube.com" target="_blank" class="social-link youtube" title="YouTube">
                            <i class="fab fa-youtube"></i>
                        </a>
                        <a href="https://linkedin.com" target="_blank" class="social-link linkedin" title="LinkedIn">
                            <i class="fab fa-linkedin-in"></i>
                        </a>
                        <a href="https://tiktok.com" target="_blank" class="social-link tiktok" title="TikTok">
                            <i class="fab fa-tiktok"></i>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Quick Links -->
            <div class="footer-column">
                <h3>Quick Links</h3>
                <ul class="footer-links">
                    <li><a href="/fashionhub/Customer/CustomerDashboard.php">Home</a></li>
                    <li><a href="/fashionhub/Customer/Products.php">Shop</a></li>
                    <li><a href="/fashionhub/Customer/CustomerOrders.php">My Orders</a></li>
                    <li><a href="/fashionhub/Customer/Cart.php">Cart</a></li>
                    <li><a href="/fashionhub/Customer/Profile.php">Profile</a></li>
                </ul>
            </div>

            <!-- Customer Service -->
            <div class="footer-column">
                <h3>Support</h3>
                <ul class="footer-links">
                    <li><a href="#">Help Center</a></li>
                    <li><a href="#">Track Order</a></li>
                    <li><a href="#">Returns</a></li>
                    <li><a href="#">Shipping Info</a></li>
                    <li><a href="#">Size Guide</a></li>
                </ul>
            </div>

            <!-- Company Info -->
            <div class="footer-column">
                <h3>Company</h3>
                <ul class="footer-links">
                    <li><a href="#">About Us</a></li>
                    <li><a href="#">Careers</a></li>
                    <li><a href="#">Blog</a></li>
                    <li><a href="#">Sustainability</a></li>
                    <li><a href="#">Press</a></li>
                </ul>
            </div>

            <!-- Newsletter & Contact -->
            <div class="footer-column">
                <h3>Stay Connected</h3>
                <div class="newsletter-section">
                    <p class="newsletter-text">
                        Subscribe to get special offers, free giveaways, and updates on new arrivals!
                    </p>
                    <form class="newsletter-form" onsubmit="handleNewsletter(event)">
                        <input type="email" class="newsletter-input" placeholder="Enter your email" required>
                        <button type="submit" class="newsletter-btn">
                            <i class="fas fa-paper-plane"></i>
                            Subscribe
                        </button>
                    </form>
                </div>

                <div class="contact-info" style="margin-top: 25px;">
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-phone"></i>
                        </div>
                        <div>
                            <strong>Call Us</strong><br>
                            +94 11 234 5678
                        </div>
                    </div>
                    <div class="contact-item">
                        <div class="contact-icon">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div>
                            <strong>Email</strong><br>
                            support@fashionhub.lk
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer Bottom -->
        <div class="footer-bottom">
            <div class="copyright">
                &copy; 2025 <a href="/fashionhub/Customer/CustomerDashboard.php">FashionHub</a>. All rights reserved. Made with <i class="fas fa-heart" style="color: #e74c3c;"></i> in Sri Lanka
            </div>
            <div class="footer-bottom-links">
                <a href="#">Privacy Policy</a>
                <a href="#">Terms of Service</a>
                <a href="#">Cookie Policy</a>
                <a href="#">Sitemap</a>
            </div>
        </div>
    </div>
</footer>

<style>
    /* Footer Styles */
    .footer {
        background: linear-gradient(135deg, #2c3e50 0%, #1a252f 100%);
        color: white;
        position: relative;
        overflow: hidden;
        margin-top: 80px;
    }

    .footer::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #e74c3c 0%, #c0392b 50%, #e74c3c 100%);
    }

    .footer-wave {
        position: absolute;
        top: -50px;
        left: 0;
        width: 100%;
        height: 60px;
        background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 120" preserveAspectRatio="none"><path d="M321.39,56.44c58-10.79,114.16-30.13,172-41.86,82.39-16.72,168.19-17.73,250.45-.39C823.78,31,906.67,72,985.66,92.83c70.05,18.48,146.53,26.09,214.34,3V0H0V27.35A600.21,600.21,0,0,0,321.39,56.44Z" fill="%232c3e50"></path></svg>') no-repeat;
        background-size: cover;
    }

    .footer-content {
        max-width: 1400px;
        margin: 0 auto;
        padding: 80px 40px 40px;
        position: relative;
        z-index: 1;
    }

    .footer-grid {
        display: grid;
        grid-template-columns: 2fr 1fr 1fr 1fr 1.5fr;
        gap: 50px;
        margin-bottom: 50px;
    }

    /* Footer Brand Section */
    .footer-brand {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }

    .footer-logo {
        display: flex;
        align-items: center;
        gap: 12px;
        font-size: 32px;
        font-weight: 900;
        color: white;
        text-decoration: none;
        margin-bottom: 10px;
    }

    .footer-logo i {
        font-size: 36px;
        color: #e74c3c;
    }

    .footer-description {
        font-size: 15px;
        line-height: 1.7;
        color: rgba(255, 255, 255, 0.8);
        margin-bottom: 20px;
    }

    .social-links {
        display: flex;
        gap: 15px;
        margin-top: 10px;
    }

    .social-link {
        width: 50px;
        height: 50px;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 20px;
        color: white;
        text-decoration: none;
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        position: relative;
        overflow: hidden;
    }

    .social-link::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: linear-gradient(135deg, rgba(255,255,255,0.1) 0%, rgba(255,255,255,0.05) 100%);
        opacity: 0;
        transition: opacity 0.3s;
    }

    .social-link:hover::before {
        opacity: 1;
    }

    .social-link:hover {
        transform: translateY(-5px);
        box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
    }

    .social-link.facebook {
        background: linear-gradient(135deg, #1877f2 0%, #0c63d4 100%);
    }

    .social-link.instagram {
        background: linear-gradient(135deg, #f58529 0%, #dd2a7b 50%, #8134af 100%);
    }

    .social-link.twitter {
        background: linear-gradient(135deg, #1da1f2 0%, #0d8bd9 100%);
    }

    .social-link.youtube {
        background: linear-gradient(135deg, #ff0000 0%, #cc0000 100%);
    }

    .social-link.linkedin {
        background: linear-gradient(135deg, #0077b5 0%, #005885 100%);
    }

    .social-link.tiktok {
        background: linear-gradient(135deg, #000000 0%, #fe2c55 100%);
    }

    /* Footer Column */
    .footer-column h3 {
        font-size: 18px;
        font-weight: 800;
        margin-bottom: 25px;
        color: white;
        position: relative;
        padding-bottom: 12px;
    }

    .footer-column h3::after {
        content: '';
        position: absolute;
        left: 0;
        bottom: 0;
        width: 40px;
        height: 3px;
        background: linear-gradient(90deg, #e74c3c 0%, #c0392b 100%);
        border-radius: 2px;
    }

    .footer-links {
        list-style: none;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .footer-links a {
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        font-size: 15px;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        gap: 8px;
        font-weight: 500;
    }

    .footer-links a::before {
        content: '\f105';
        font-family: 'Font Awesome 6 Free';
        font-weight: 900;
        color: #e74c3c;
        opacity: 0;
        transform: translateX(-10px);
        transition: all 0.3s;
    }

    .footer-links a:hover {
        color: white;
        transform: translateX(5px);
    }

    .footer-links a:hover::before {
        opacity: 1;
        transform: translateX(0);
    }

    /* Newsletter Section */
    .newsletter-section {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }

    .newsletter-text {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.8);
        line-height: 1.6;
    }

    .newsletter-form {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .newsletter-input {
        padding: 14px 18px;
        border: 2px solid rgba(255, 255, 255, 0.2);
        border-radius: 10px;
        background: rgba(255, 255, 255, 0.1);
        color: white;
        font-size: 14px;
        transition: all 0.3s;
        font-weight: 500;
    }

    .newsletter-input::placeholder {
        color: rgba(255, 255, 255, 0.5);
    }

    .newsletter-input:focus {
        outline: none;
        border-color: #e74c3c;
        background: rgba(255, 255, 255, 0.15);
    }

    .newsletter-btn {
        padding: 14px 24px;
        background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        cursor: pointer;
        transition: all 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        gap: 8px;
    }

    .newsletter-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 25px rgba(231, 76, 60, 0.4);
    }

    /* Contact Info */
    .contact-info {
        display: flex;
        flex-direction: column;
        gap: 18px;
    }

    .contact-item {
        display: flex;
        align-items: flex-start;
        gap: 15px;
        color: rgba(255, 255, 255, 0.8);
        font-size: 14px;
        line-height: 1.6;
    }

    .contact-icon {
        width: 40px;
        height: 40px;
        border-radius: 10px;
        background: rgba(231, 76, 60, 0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        color: #e74c3c;
        font-size: 16px;
        flex-shrink: 0;
    }

    /* Footer Bottom */
    .footer-bottom {
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        padding: 30px 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
        gap: 20px;
    }

    .copyright {
        font-size: 14px;
        color: rgba(255, 255, 255, 0.6);
        font-weight: 500;
    }

    .copyright a {
        color: #e74c3c;
        text-decoration: none;
        font-weight: 700;
    }

    .copyright a:hover {
        text-decoration: underline;
    }

    .footer-bottom-links {
        display: flex;
        gap: 25px;
        flex-wrap: wrap;
    }

    .footer-bottom-links a {
        color: rgba(255, 255, 255, 0.6);
        text-decoration: none;
        font-size: 14px;
        font-weight: 500;
        transition: color 0.3s;
    }

    .footer-bottom-links a:hover {
        color: white;
    }

    /* Responsive Design */
    @media (max-width: 1200px) {
        .footer-grid {
            grid-template-columns: repeat(3, 1fr);
        }

        .footer-brand {
            grid-column: 1 / -1;
            max-width: 600px;
        }
    }

    @media (max-width: 768px) {
        .footer-content {
            padding: 60px 20px 30px;
        }

        .footer-grid {
            grid-template-columns: 1fr;
            gap: 40px;
        }

        .footer-brand {
            text-align: center;
            align-items: center;
        }

        .social-links {
            justify-content: center;
        }

        .footer-column h3::after {
            left: 50%;
            transform: translateX(-50%);
        }

        .footer-column {
            text-align: center;
        }

        .footer-links {
            align-items: center;
        }

        .footer-bottom {
            flex-direction: column;
            text-align: center;
        }
    }

    @media (max-width: 480px) {
        .footer-logo {
            font-size: 28px;
        }

        .footer-logo i {
            font-size: 32px;
        }

        .social-links {
            flex-wrap: wrap;
        }
    }
</style>

<script>
    function handleNewsletter(event) {
        event.preventDefault();
        const email = event.target.querySelector('input').value;
        alert(`Thank you for subscribing with: ${email}\n\nYou'll receive our latest updates and exclusive offers!`);
        event.target.reset();
    }

    // Add smooth scroll animation when clicking footer links
    document.querySelectorAll('.footer-links a').forEach(link => {
        link.addEventListener('click', function(e) {
            if (this.getAttribute('href').startsWith('#')) {
                e.preventDefault();
            }
        });
    });

    // Social link click tracking (optional)
    document.querySelectorAll('.social-link').forEach(link => {
        link.addEventListener('click', function() {
            console.log('Social link clicked:', this.title);
        });
    });
</script>