/**
 * LOAN81 - Master JavaScript Controller
 * Dynamic Calculations, Interactive Sliders, Lead Wizard, and UX Handlers
 */

document.addEventListener('DOMContentLoaded', () => {
  initMobileMenu();
  initHeroSliders();
  initEmiCalculator();
  initFaqAccordion();
  initDocsTabs();
  initLeadModal();
  initFormSubmissions();
});

/* ==========================================================================
   1. MOBILE MENU & HEADER SCROLL
   ========================================================================== */
function initMobileMenu() {
  const mobileToggle = document.querySelector('.mobile-toggle');
  const navMenu = document.querySelector('.nav-menu');
  const header = document.querySelector('.main-header');

  if (mobileToggle && navMenu) {
    mobileToggle.addEventListener('click', () => {
      navMenu.classList.toggle('active');
      const isExpanded = navMenu.classList.contains('active');
      mobileToggle.setAttribute('aria-expanded', isExpanded);
    });

    // Close menu when clicking outside or on a link
    document.addEventListener('click', (e) => {
      if (!navMenu.contains(e.target) && !mobileToggle.contains(e.target) && navMenu.classList.contains('active')) {
        navMenu.classList.remove('active');
      }
    });

    navMenu.querySelectorAll('.nav-link').forEach(link => {
      link.addEventListener('click', () => {
        navMenu.classList.remove('active');
      });
    });
  }

  // Header scroll elevation
  window.addEventListener('scroll', () => {
    if (header) {
      if (window.scrollY > 20) {
        header.style.boxShadow = '0 4px 20px rgba(0, 0, 0, 0.08)';
      } else {
        header.style.boxShadow = '0 2px 15px rgba(0, 0, 0, 0.05)';
      }
    }
  });
}

/* ==========================================================================
   2. HERO QUICK LEAD SLIDER
   ========================================================================== */
function initHeroSliders() {
  const heroSlider = document.getElementById('heroLoanAmountSlider');
  const heroDisplay = document.getElementById('heroLoanAmountDisplay');

  if (heroSlider && heroDisplay) {
    const updateHeroDisplay = () => {
      const val = parseInt(heroSlider.value, 10);
      heroDisplay.textContent = '₹' + formatIndianCurrency(val);
      updateSliderTrack(heroSlider);
    };

    heroSlider.addEventListener('input', updateHeroDisplay);
    updateHeroDisplay();
  }
}

/* ==========================================================================
   3. INTERACTIVE EMI & LOAN CALCULATOR
   ========================================================================== */
function initEmiCalculator() {
  // Tabs
  const tabBtns = document.querySelectorAll('.calc-tab-btn');
  const monthlyCalcWrap = document.getElementById('monthlyCalcWrap');
  const flexiCalcWrap = document.getElementById('flexiCalcWrap');

  if (tabBtns.length > 0) {
    tabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        tabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const mode = btn.getAttribute('data-mode');
        if (mode === 'flexi') {
          if (monthlyCalcWrap) monthlyCalcWrap.style.display = 'none';
          if (flexiCalcWrap) flexiCalcWrap.style.display = 'block';
          calculateFlexiLoan();
        } else {
          if (monthlyCalcWrap) monthlyCalcWrap.style.display = 'block';
          if (flexiCalcWrap) flexiCalcWrap.style.display = 'none';
          calculateMonthlyEmi();
        }
      });
    });
  }

  // Monthly Sliders
  const monthlyAmount = document.getElementById('emiAmount');
  const monthlyRate = document.getElementById('emiRate');
  const monthlyTenure = document.getElementById('emiTenure');

  // Monthly Displays
  const emiAmountDisplay = document.getElementById('emiAmountDisplay');
  const emiRateDisplay = document.getElementById('emiRateDisplay');
  const emiTenureDisplay = document.getElementById('emiTenureDisplay');

  // Summary Elements
  const summaryMonthlyEmi = document.getElementById('summaryMonthlyEmi');
  const summaryPrincipal = document.getElementById('summaryPrincipal');
  const summaryInterest = document.getElementById('summaryInterest');
  const summaryTotalPayable = document.getElementById('summaryTotalPayable');

  function calculateMonthlyEmi() {
    if (!monthlyAmount || !monthlyRate || !monthlyTenure) return;

    const P = parseFloat(monthlyAmount.value);
    const annualRate = parseFloat(monthlyRate.value);
    const months = parseInt(monthlyTenure.value, 10);

    // Update displays
    if (emiAmountDisplay) emiAmountDisplay.textContent = '₹' + formatIndianCurrency(P);
    if (emiRateDisplay) emiRateDisplay.textContent = annualRate.toFixed(1) + '%';
    if (emiTenureDisplay) emiTenureDisplay.textContent = months + ' Months (' + (months / 12).toFixed(1) + ' Yrs)';

    updateSliderTrack(monthlyAmount);
    updateSliderTrack(monthlyRate);
    updateSliderTrack(monthlyTenure);

    // Formula: E = P * r * (1+r)^n / ((1+r)^n - 1)
    const r = (annualRate / 12) / 100;
    let emi = 0;
    if (r === 0) {
      emi = P / months;
    } else {
      emi = (P * r * Math.pow(1 + r, months)) / (Math.pow(1 + r, months) - 1);
    }

    const totalPayable = emi * months;
    const totalInterest = totalPayable - P;

    if (summaryMonthlyEmi) summaryMonthlyEmi.textContent = '₹' + formatIndianCurrency(Math.round(emi));
    if (summaryPrincipal) summaryPrincipal.textContent = '₹' + formatIndianCurrency(Math.round(P));
    if (summaryInterest) summaryInterest.textContent = '₹' + formatIndianCurrency(Math.round(totalInterest));
    if (summaryTotalPayable) summaryTotalPayable.textContent = '₹' + formatIndianCurrency(Math.round(totalPayable));
  }

  if (monthlyAmount && monthlyRate && monthlyTenure) {
    [monthlyAmount, monthlyRate, monthlyTenure].forEach(input => {
      input.addEventListener('input', calculateMonthlyEmi);
    });
    calculateMonthlyEmi();
  }

  // Flexi Short-Term Calculator (Days-based)
  const flexiAmount = document.getElementById('flexiAmount');
  const flexiDays = document.getElementById('flexiDays');
  const flexiRate = document.getElementById('flexiRate');

  const flexiAmountDisplay = document.getElementById('flexiAmountDisplay');
  const flexiDaysDisplay = document.getElementById('flexiDaysDisplay');
  const flexiRateDisplay = document.getElementById('flexiRateDisplay');

  const flexiTotalPayable = document.getElementById('flexiTotalPayable');
  const flexiTotalInterest = document.getElementById('flexiTotalInterest');

  function calculateFlexiLoan() {
    if (!flexiAmount || !flexiDays || !flexiRate) return;

    const P = parseFloat(flexiAmount.value);
    const days = parseInt(flexiDays.value, 10);
    const dailyRate = parseFloat(flexiRate.value);

    if (flexiAmountDisplay) flexiAmountDisplay.textContent = '₹' + formatIndianCurrency(P);
    if (flexiDaysDisplay) flexiDaysDisplay.textContent = days + ' Days';
    if (flexiRateDisplay) flexiRateDisplay.textContent = dailyRate.toFixed(2) + '% / Day';

    updateSliderTrack(flexiAmount);
    updateSliderTrack(flexiDays);
    updateSliderTrack(flexiRate);

    const interest = P * (dailyRate / 100) * days;
    const total = P + interest;

    if (flexiTotalInterest) flexiTotalInterest.textContent = '₹' + formatIndianCurrency(Math.round(interest));
    if (flexiTotalPayable) flexiTotalPayable.textContent = '₹' + formatIndianCurrency(Math.round(total));
  }

  if (flexiAmount && flexiDays && flexiRate) {
    [flexiAmount, flexiDays, flexiRate].forEach(input => {
      input.addEventListener('input', calculateFlexiLoan);
    });
  }
}

function updateSliderTrack(slider) {
  const min = parseFloat(slider.min) || 0;
  const max = parseFloat(slider.max) || 100;
  const val = parseFloat(slider.value) || 0;
  const percentage = ((val - min) / (max - min)) * 100;
  slider.style.background = `linear-gradient(to right, #0052ff 0%, #0052ff ${percentage}%, #e2e8f0 ${percentage}%, #e2e8f0 100%)`;
}

function formatIndianCurrency(num) {
  return Number(num).toLocaleString('en-IN');
}

/* ==========================================================================
   4. FAQ ACCORDION
   ========================================================================== */
function initFaqAccordion() {
  const faqItems = document.querySelectorAll('.faq-item');

  faqItems.forEach(item => {
    const questionBtn = item.querySelector('.faq-question');
    const answer = item.querySelector('.faq-answer');

    if (questionBtn && answer) {
      questionBtn.addEventListener('click', () => {
        const isActive = item.classList.contains('active');

        // Close other items
        faqItems.forEach(other => {
          if (other !== item) {
            other.classList.remove('active');
            const otherAns = other.querySelector('.faq-answer');
            if (otherAns) otherAns.style.maxHeight = null;
          }
        });

        // Toggle current
        if (!isActive) {
          item.classList.add('active');
          answer.style.maxHeight = answer.scrollHeight + 'px';
        } else {
          item.classList.remove('active');
          answer.style.maxHeight = null;
        }
      });
    }
  });
}

/* ==========================================================================
   5. ELIGIBILITY & DOCUMENT CHECKLIST TABS
   ========================================================================== */
function initDocsTabs() {
  const docTabBtns = document.querySelectorAll('.docs-tab-btn');
  const salariedView = document.getElementById('docsSalariedView');
  const selfEmployedView = document.getElementById('docsSelfEmployedView');

  if (docTabBtns.length > 0) {
    docTabBtns.forEach(btn => {
      btn.addEventListener('click', () => {
        docTabBtns.forEach(b => b.classList.remove('active'));
        btn.classList.add('active');

        const target = btn.getAttribute('data-target');
        if (target === 'self-employed') {
          if (salariedView) salariedView.style.display = 'none';
          if (selfEmployedView) selfEmployedView.style.display = 'grid';
        } else {
          if (salariedView) salariedView.style.display = 'grid';
          if (selfEmployedView) selfEmployedView.style.display = 'none';
        }
      });
    });
  }
}

/* ==========================================================================
   6. MULTI-STEP MODAL LEAD APPLICATION WIZARD
   ========================================================================== */
function initLeadModal() {
  const modal = document.getElementById('leadModalOverlay');
  const openButtons = document.querySelectorAll('.open-lead-modal');
  const closeBtn = document.getElementById('closeModalBtn');
  const nextBtns = document.querySelectorAll('.wizard-next-btn');
  const prevBtns = document.querySelectorAll('.wizard-prev-btn');
  const steps = document.querySelectorAll('.wizard-step');
  const progressBars = document.querySelectorAll('.progress-step');

  let currentStep = 1;

  function setStep(stepNum) {
    currentStep = stepNum;
    steps.forEach(s => s.classList.remove('active'));
    progressBars.forEach((p, idx) => {
      if (idx < stepNum) {
        p.classList.add('active');
      } else {
        p.classList.remove('active');
      }
    });

    const targetStepEl = document.getElementById(`wizardStep${stepNum}`);
    if (targetStepEl) targetStepEl.classList.add('active');
  }

  function openModal(prefilledLoanType = '') {
    if (modal) {
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      if (prefilledLoanType) {
        const loanSelect = document.getElementById('wizardLoanType');
        if (loanSelect) loanSelect.value = prefilledLoanType;
      }
      setStep(1);
    }
  }

  function closeModal() {
    if (modal) {
      modal.classList.remove('active');
      document.body.style.overflow = '';
    }
  }

  openButtons.forEach(btn => {
    btn.addEventListener('click', (e) => {
      e.preventDefault();
      const loanType = btn.getAttribute('data-loan-type') || '';
      openModal(loanType);
    });
  });

  if (closeBtn) closeBtn.addEventListener('click', closeModal);

  if (modal) {
    modal.addEventListener('click', (e) => {
      if (e.target === modal) closeModal();
    });
  }

  nextBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      if (validateStep(currentStep)) {
        if (currentStep < 3) {
          setStep(currentStep + 1);
        }
      }
    });
  });

  prevBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      if (currentStep > 1) {
        setStep(currentStep - 1);
      }
    });
  });

  function validateStep(step) {
    if (step === 1) {
      const loanType = document.getElementById('wizardLoanType');
      const amount = document.getElementById('wizardLoanAmount');
      if (loanType && !loanType.value) {
        alert('Please select a loan type.');
        return false;
      }
      if (amount && (!amount.value || amount.value < 10000)) {
        alert('Please enter a valid loan amount (min ₹10,000).');
        return false;
      }
    } else if (step === 2) {
      const city = document.getElementById('wizardCity');
      const income = document.getElementById('wizardMonthlyIncome');
      if (city && !city.value.trim()) {
        alert('Please enter your city/location.');
        return false;
      }
      if (income && !income.value) {
        alert('Please enter your estimated monthly income.');
        return false;
      }
    }
    return true;
  }
}

/* ==========================================================================
   7. FORM SUBMISSIONS & WHATSAPP BRIDGE
   ========================================================================== */
function initFormSubmissions() {
  // Hero Quick Form
  const heroForm = document.getElementById('heroQuickForm');
  if (heroForm) {
    heroForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const loanType = document.getElementById('heroLoanType').value;
      const amount = document.getElementById('heroLoanAmountSlider').value;
      const phone = document.getElementById('heroPhone').value;
      const city = document.getElementById('heroCity').value;

      if (!phone || phone.length < 10) {
        alert('Please enter a valid 10-digit mobile number.');
        return;
      }

      handleLeadSuccess({
        source: 'Hero Quick Form',
        loanType,
        amount,
        phone,
        city
      });
    });
  }

  // Wizard Final Submit
  const wizardForm = document.getElementById('wizardLeadForm');
  if (wizardForm) {
    wizardForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const name = document.getElementById('wizardFullName').value;
      const phone = document.getElementById('wizardPhone').value;
      const loanType = document.getElementById('wizardLoanType').value;
      const amount = document.getElementById('wizardLoanAmount').value;
      const city = document.getElementById('wizardCity').value;

      if (!phone || phone.length < 10) {
        alert('Please enter a valid 10-digit mobile number.');
        return;
      }

      handleLeadSuccess({
        source: 'Multi-Step Modal Wizard',
        name,
        phone,
        loanType,
        amount,
        city
      });
    });
  }

  // Contact Page Form
  const contactForm = document.getElementById('contactPageForm');
  if (contactForm) {
    contactForm.addEventListener('submit', (e) => {
      e.preventDefault();
      const name = document.getElementById('contactName').value;
      const phone = document.getElementById('contactPhone').value;
      const email = document.getElementById('contactEmail').value;
      const message = document.getElementById('contactMessage').value;

      handleLeadSuccess({
        source: 'Contact Page Inquiry',
        name,
        phone,
        email,
        message
      });
    });
  }
}

function handleLeadSuccess(data) {
  // Asynchronously send lead to database
  try {
    fetch('api/submit_lead.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(data)
    }).catch(err => console.log('Lead sync noted:', err));
  } catch (e) {
    // Graceful fallback
  }

  // Display confirmation alert or toast
  const message = `Thank you! Your loan inquiry has been submitted to Loan81 advisors.\n\nOur senior loan advisor will contact you at +91 ${data.phone} within 15-30 minutes.`;
  alert(message);

  // Close modal if open
  const modal = document.getElementById('leadModalOverlay');
  if (modal) modal.classList.remove('active');
  document.body.style.overflow = '';

  // Direct option to continue on WhatsApp
  const proceedToWhatsApp = confirm("Would you like to connect immediately with our senior advisor on WhatsApp for instant approval assistance?");
  if (proceedToWhatsApp) {
    const waText = encodeURIComponent(
      `Hello Loan81! I need assistance with a loan.\n\n` +
      `• Loan Type: ${data.loanType || 'General Loan'}\n` +
      `• Required Amount: ₹${data.amount ? formatIndianCurrency(data.amount) : 'Discuss on call'}\n` +
      `• City: ${data.city || 'Delhi NCR'}\n` +
      `• My Contact: ${data.phone}\n\nPlease share the best bank offers and eligibility.`
    );
    window.open(`https://wa.me/918368250300?text=${waText}`, '_blank');
  }
}

