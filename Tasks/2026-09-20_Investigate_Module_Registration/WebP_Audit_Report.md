# Technical Audit Report: Image Upload Pipeline & WebP Conversion

**Date:** 2026-09-20
**Module Audited:** `event_registration.php` and `image_utils.php`
**Auditor:** God Mode Lead Architect
**Subject:** Discrepancy between expected Client-Side WebP conversion and actual Server-Side implementation.

## 1. Executive Summary
The client raised a concern: *"Since users are allowed to upload PNG/JPG files, why aren't they converted into WebP on their device before being uploaded? We applied this previously, why is it not functional?"*

An immediate technical audit of the codebase was conducted. The audit reveals that **WebP conversion is fully functional, but it is currently implemented as a Server-Side process, not a Client-Side process.** 

## 2. Technical Findings

### Finding A: The Current WebP Implementation (Server-Side)
The codebase *does* have a WebP conversion engine built-in, which is why the client recalls it being applied. However, it exists entirely in the PHP backend.
- **File Location:** `image_utils.php` (Lines 7-159)
- **Function Used:** `save_image_as_webp()`
- **Mechanism:** When the user hits "Submit", the browser sends the **original, uncompressed JPG/PNG** over the internet to the server. Once the server receives the massive payload, PHP's GD library (`imagewebp()`) converts it into a tiny WebP file to save hard drive space.
- **The Flaw:** Because the compression happens *after* the file travels over the internet, a user uploading 40MB of original photos will still crash the server's 8MB network receiving limit (`post_max_size`), causing the "network communication error" before the PHP script can even begin the WebP conversion.

### Finding B: The Frontend Submission Flow (Client-Side)
We audited the frontend JavaScript responsible for transmitting the images from the user's device.
- **File Location:** `event_registration.php` (Lines 913-918)
- **Code Snippet:** 
  ```javascript
  const formData = new FormData(this);
  fetch('event_registration.php', { method: 'POST', body: formData })
  ```
- **Observation:** The JavaScript natively packages the raw `<input type="file">` contents directly into the `FormData` object. There is absolutely no HTML5 `<canvas>` rendering, no Blob manipulation, and no JavaScript-based compression happening inside the user's browser prior to the `fetch()` call. 

## 3. Root Cause of the Misunderstanding
The phrase "we applied it" refers to the successful integration of `image_utils.php` during a previous sprint to save server storage space. It was designed as a **Storage Optimization** tactic, not a **Network Transmission Optimization** tactic.

## 4. Remediation Plan (God Mode Recommendation)
To achieve the client's expected behavior (converting to WebP *on the user's phone* before it hits the network), the following architectural shift is required:

1. **Remove the 800KB Frontend Hard Limit:** Allow users to select large 5MB+ photos from their camera rolls.
2. **Implement JS Interceptor:** Add a JavaScript image compression library (or native Canvas API logic) to the frontend `event_registration.php`.
3. **Pre-flight Conversion:** When the user clicks "Submit", the JS interceptor reads the raw JPG/PNG from the device memory, draws it to an invisible Canvas, compresses it down to a 100KB WebP Blob, and injects *that* Blob into the `FormData`.
4. **Transmission:** The browser transmits the 100KB payload instead of the 5MB payload. 
5. **Result:** Zero network errors, zero server rejections, and a seamless user experience.

---
*End of Audit Report*
