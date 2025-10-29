/**
 * Subscription Price Display
 *
 * Handles real-time price updates when subscription options or quantity changes.
 *
 * @package Progressus\MightyKids
 * @since 1.7.0
 */

(function ($) {
  "use strict";

  // Ensure the localized data exists.
  if (typeof mkDynamicPricing === "undefined") {
    console.warn("mkDynamicPricing data not found");
    return;
  }

  // Debug mode (only logs if WP_DEBUG is enabled)
  const DEBUG = mkDynamicPricing.debug || false;
  const log = function (message, ...args) {
    if (DEBUG) {
      console.log(message, ...args);
    }
  };

  /**
   * Price Calculator
   */
  const PriceCalculator = {
    /**
     * Current state
     */
    state: {
      basePrice: parseFloat(mkDynamicPricing.basePrice) || 0,
      regularPrice: parseFloat(mkDynamicPricing.regularPrice) || 0,
      quantity: 1,
      isSubscription: false,
      isUpdating: false,
    },

    /**
     * Initialize the calculator
     */
    init: function () {
      this.bindEvents();
      this.updateDisplay();

      log("✅ Subscription Price Display initialized", this.state);
    },

    /**
     * Bind event listeners
     */
    bindEvents: function () {
      const self = this;

      // Quantity changes
      $(document).on("change input", "input.qty", function () {
        const newQty = parseInt($(this).val()) || 1;
        if (newQty !== self.state.quantity) {
          self.state.quantity = newQty;
          self.updateDisplay();
        }
      });

      // Subscription option changes (radio buttons)
      $(document).on("change", 'input[name^="convert_to_sub_"]', function () {
        setTimeout(function () {
          self.checkSubscriptionStatus();
          self.updateDisplay();
        }, 100);
      });

      // Subscription option changes (dropdown)
      $(document).on(
        "change",
        'select[name^="convert_to_sub_dropdown"]',
        function () {
          setTimeout(function () {
            self.checkSubscriptionStatus();
            self.updateDisplay();
          }, 100);
        }
      );

      // Subscription prompt checkbox/radio
      $(document).on(
        "change",
        ".wcsatt-options-prompt-action-input",
        function () {
          setTimeout(function () {
            self.checkSubscriptionStatus();
            self.updateDisplay();
          }, 100);
        }
      );

      // Custom subscription toggle (Mighty Kids specific)
      $(document).on("change", ".mk-subscription-toggle-input", function () {
        const isChecked = $(this).is(":checked");
        log("🔄 Custom toggle changed:", isChecked);

        // Show/hide ALL subscription-related elements
        const $optionsWrapper = $(".wcsatt-options-product-wrapper");
        const $addToSubForm = $(".jgtb-add-to-subscription");
        const $addToExistingWrapper = $(".wcsatt-add-to-subscription-wrapper");
        const $firstPaymentDate = $(".first-payment-date");

        if (isChecked) {
          // CRITICAL: Select the FIRST subscription radio button (not "0")
          const $subscriptionRadios = $('input[name^="convert_to_sub_"]');
          const $firstSubRadio = $subscriptionRadios
            .filter('[value!="0"]')
            .first();

          if ($firstSubRadio.length > 0) {
            log("✅ Activating subscription radio:", $firstSubRadio.val());
            $firstSubRadio.prop("checked", true).trigger("change");
          }

          // Use css() to override display: none
          $optionsWrapper.css("display", "block").hide().slideDown(200);
          $addToSubForm.css("display", "block").hide().slideDown(200);
          $addToExistingWrapper.css("display", "block").hide().slideDown(200);
          $firstPaymentDate.css("display", "block").hide().slideDown(200);
        } else {
          // CRITICAL: Select the "one-time" option (value="0")
          const $oneTimeRadio = $('input[name^="convert_to_sub_"][value="0"]');

          if ($oneTimeRadio.length > 0) {
            log("✅ Activating one-time option");
            $oneTimeRadio.prop("checked", true).trigger("change");
          }

          $optionsWrapper.slideUp(200, function () {
            $(this).css("display", "none");
          });
          $addToSubForm.slideUp(200, function () {
            $(this).css("display", "none");
          });
          $addToExistingWrapper.slideUp(200, function () {
            $(this).css("display", "none");
          });
          $firstPaymentDate.slideUp(200, function () {
            $(this).css("display", "none");
          });
        }

        setTimeout(function () {
          self.checkSubscriptionStatus();
          self.updateDisplay();
        }, 100);
      });

      // Variation changes
      if (mkDynamicPricing.isVariable) {
        $(document).on(
          "found_variation",
          ".variations_form",
          function (event, variation) {
            log("🔄 Variation found", variation);
            self.state.basePrice = parseFloat(variation.display_price) || 0;
            self.state.regularPrice =
              parseFloat(variation.display_regular_price) ||
              parseFloat(variation.display_price) ||
              0;
            self.updateDisplay();
          }
        );

        $(document).on("reset_data", ".variations_form", function () {
          log("🔄 Variation reset");
          self.state.basePrice = 0;
          self.state.regularPrice = 0;
          self.updateDisplay();
        });
      }
    },

    /**
     * Check if subscription is currently selected
     */
    checkSubscriptionStatus: function () {
      let isActive = false;

      // Check radio buttons
      const $radioChecked = $('input[name^="convert_to_sub_"]:checked');
      if ($radioChecked.length > 0) {
        const value = $radioChecked.val();
        // Value "0" means one-time purchase, anything else is a subscription
        isActive = value !== "0" && value !== "";
      }

      // Check prompt checkbox
      const $checkbox = $(
        '.wcsatt-options-prompt-action-input[type="checkbox"]'
      );
      if ($checkbox.length > 0) {
        isActive = $checkbox.is(":checked");
      }

      // Check custom Mighty Kids toggle
      const $customToggle = $(".mk-subscription-toggle-input");
      if ($customToggle.length > 0) {
        isActive = $customToggle.is(":checked");
      }

      // Check prompt radio
      const $radioPrompt = $(
        '.wcsatt-options-prompt-action-input[type="radio"]:checked'
      );
      if ($radioPrompt.length > 0) {
        isActive = $radioPrompt.val() === "yes";
      }

      this.state.isSubscription = isActive;
      log("📋 Subscription status:", isActive);
    },

    /**
     * Calculate bulk discount for given quantity
     */
    calculateBulkDiscount: function (price, quantity) {
      // Check if bulk discounts are enabled globally
      if (!mkDynamicPricing.bulkDiscountsEnabled) {
        log("⛔ Bulk discounts disabled in admin settings");
        return price;
      }

      if (quantity <= 1 || !mkDynamicPricing.discountTiers) {
        return price;
      }

      let discountedPrice = price;

      // Apply tiered discounts
      mkDynamicPricing.discountTiers.forEach(function (tier) {
        if (quantity >= tier.min && quantity <= tier.max) {
          discountedPrice = price * (1 - tier.discount / 100);
          log(`💰 Applied ${tier.discount}% bulk discount (qty: ${quantity})`);
        }
      });

      return discountedPrice;
    },

    /**
     * Get the discount for the currently selected subscription scheme
     */
    getActiveSchemeDiscount: function () {
      // If no schemes data, use default discount
      if (
        !mkDynamicPricing.subscriptionSchemes ||
        Object.keys(mkDynamicPricing.subscriptionSchemes).length === 0
      ) {
        return mkDynamicPricing.subscriptionDiscount || 0;
      }

      // Find which subscription radio/select is checked
      const $activeRadio = $('input[name^="convert_to_sub_"]:checked');

      if ($activeRadio.length > 0) {
        const selectedValue = $activeRadio.val();

        // Value "0" means one-time purchase (no subscription)
        if (selectedValue === "0" || selectedValue === 0) {
          return 0;
        }

        // Check if this scheme has a specific discount
        if (mkDynamicPricing.subscriptionSchemes[selectedValue]) {
          const schemeDiscount =
            mkDynamicPricing.subscriptionSchemes[selectedValue].discount;
          log(
            `🎯 Using scheme-specific discount: ${schemeDiscount}% for scheme ${selectedValue}`
          );
          return schemeDiscount;
        }
      }

      // Fallback to default subscription discount
      return mkDynamicPricing.subscriptionDiscount || 0;
    },

    /**
     * Calculate subscription discount
     */
    calculateSubscriptionDiscount: function (price) {
      if (!this.state.isSubscription) {
        return price;
      }

      const discount = this.getActiveSchemeDiscount();

      if (discount <= 0) {
        return price;
      }

      const discountedPrice = price * (1 - discount / 100);
      log(`🔄 Applied ${discount}% subscription discount`);

      return discountedPrice;
    },

    /**
     * Calculate final price with all discounts
     */
    calculateFinalPrice: function () {
      if (this.state.basePrice === 0) {
        return {
          final: 0,
          original: 0,
          savings: 0,
          hasDiscount: false,
        };
      }

      let price = this.state.basePrice;
      const originalPrice = this.state.regularPrice || this.state.basePrice;

      // Step 1: Apply subscription discount first
      price = this.calculateSubscriptionDiscount(price);

      // Step 2: Apply bulk discount on the discounted price
      price = this.calculateBulkDiscount(price, this.state.quantity);

      const savings = originalPrice - price;
      const hasDiscount = savings > 0;

      log("📊 Price calculation:", {
        original: originalPrice,
        final: price,
        savings: savings,
        quantity: this.state.quantity,
        isSubscription: this.state.isSubscription,
      });

      return {
        final: price,
        original: originalPrice,
        savings: savings,
        hasDiscount: hasDiscount,
      };
    },

    /**
     * Format price according to WooCommerce settings
     */
    formatPrice: function (price) {
      const format = mkDynamicPricing.currencyFormat;
      const decimals = parseInt(format.decimals) || 2;
      const decimalSep = format.decimal_sep || ",";
      const thousandSep = format.thousand_sep || ".";
      const symbol = format.symbol || "€";

      // Format number
      const parts = price.toFixed(decimals).split(".");
      parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSep);
      const formatted = parts.join(decimalSep);

      // Apply currency position
      let priceHtml = "";
      switch (format.currency_pos) {
        case "left":
          priceHtml = symbol + formatted;
          break;
        case "right":
          priceHtml = formatted + symbol;
          break;
        case "left_space":
          priceHtml = symbol + " " + formatted;
          break;
        case "right_space":
        default:
          priceHtml = formatted + " " + symbol;
          break;
      }

      return priceHtml;
    },

    /**
     * Update the price display
     */
    updateDisplay: function () {
      if (this.state.isUpdating) {
        return;
      }

      this.state.isUpdating = true;

      const priceData = this.calculateFinalPrice();
      const $priceContainer = $(".mk-dynamic-price-amount");
      const $savingsContainer = $(".mk-dynamic-price-savings");

      if (priceData.final === 0) {
        $priceContainer.html(
          '<span style="color: #999;">Please select options</span>'
        );
        $savingsContainer.html("");
        this.state.isUpdating = false;
        return;
      }

      // Calculate total prices (unit price × quantity)
      const totalFinalPrice = priceData.final * this.state.quantity;
      const totalOriginalPrice = priceData.original * this.state.quantity;
      const totalSavings = priceData.savings * this.state.quantity;

      // Build price HTML
      let priceHtml = "";

      if (priceData.hasDiscount) {
        priceHtml += '<del style="opacity: 0.6; margin-right: 0.5rem;">';
        priceHtml += this.formatPrice(totalOriginalPrice);
        priceHtml += "</del>";
      }

      priceHtml += '<span class="mk-final-price">';
      priceHtml += this.formatPrice(totalFinalPrice);
      priceHtml += "</span>";

      // Add unit price info if quantity > 1
      if (this.state.quantity > 1) {
        priceHtml += "<small>";
        priceHtml += this.formatPrice(priceData.final) + "/unit";
        priceHtml += "</small>";
      }

      // Update price
      $priceContainer.html(priceHtml);

      // Update savings info
      if (priceData.hasDiscount) {
        let savingsText = `You save ${this.formatPrice(totalSavings)}`;

        const discountTypes = [];
        if (this.state.quantity > 1) {
          discountTypes.push("bulk discount");
        }
        if (this.state.isSubscription) {
          discountTypes.push("subscription");
        }

        if (discountTypes.length > 0) {
          savingsText += " with " + discountTypes.join(" + ");
        }

        $savingsContainer.html(savingsText);
      } else {
        $savingsContainer.html("");
      }

      this.state.isUpdating = false;
    },
  };

  /**
   * Initialize when DOM is ready
   */
  $(document).ready(function () {
    log("🔍 Checking for .mk-dynamic-price-wrapper...");
    const $wrapper = $(".mk-dynamic-price-wrapper");
    log("Found wrappers:", $wrapper.length);

    // Only initialize on product pages
    if ($wrapper.length > 0) {
      log("✅ Initializing PriceCalculator...");
      PriceCalculator.init();
    } else {
      log("⚠️ No .mk-dynamic-price-wrapper found on page");
    }
  });
})(jQuery);
