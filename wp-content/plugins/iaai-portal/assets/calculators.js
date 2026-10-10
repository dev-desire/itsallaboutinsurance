(function () {
  'use strict';
  function calculate(kind, v) {
    if (v.some(x => !Number.isFinite(x) || x < 0)) throw Error('Enter valid non-negative numbers.');
    if (kind === 'term') return Math.max(0, v[0] * v[1] + v[2] + v[3] - v[4]);
    if (kind === 'salary') {
      if (v[1] + v[2] + v[3] > v[0]) throw Error('Deductions exceed monthly gross salary.');
      return v[0] - v[1] - v[2] - v[3];
    }
    if (kind === 'ulip') {
      if (v[1] > 40 || v[1] < 1 || !Number.isInteger(v[1]) || v[2] > 30 || v[3] > 30) throw Error('Use 1–40 whole years and rates from 0–30%.');
      let total = 0, rate = (v[2] - v[3]) / 100;
      for (let year = 0; year < v[1]; year++) total = (total + v[0]) * (1 + rate);
      return total;
    }
    if (kind === 'tax') {
      if (v[0] > 5000000) throw Error('This simplified calculator supports taxable income up to ₹50 lakh.');
      let tax = 0, previous = 0;
      [[400000,0],[800000,.05],[1200000,.1],[1600000,.15],[2000000,.2],[2400000,.25],[5000000,.3]].forEach(([limit,rate]) => { tax += Math.max(0,Math.min(v[0],limit)-previous)*rate; previous=limit; });
      if (v[0] <= 1200000) tax = 0;
      else tax = Math.min(tax, v[0] - 1200000);
      return Math.round(tax * 1.04 / 10) * 10;
    }
    throw Error('Unknown calculator.');
  }
  if (typeof module !== 'undefined' && module.exports) module.exports = calculate;
  if (typeof document === 'undefined') return;
  document.addEventListener('submit', function (event) {
    const form=event.target.closest('[data-iaai-calculator]'); if (!form) return;
    event.preventDefault(); const out=form.querySelector('output');
    try { const result=calculate(form.dataset.iaaiCalculator,Array.from(form.querySelectorAll('input')).map(x=>Number(x.value))); out.textContent=new Intl.NumberFormat('en-IN',{style:'currency',currency:'INR',maximumFractionDigits:0}).format(result); }
    catch (error) { out.textContent=error.message; }
  });
})();
