const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
let current='hangman', rounds={}, access={hangman:5,blackjack:5,unlimited:false}, busy=false, ready=false, recovery=null, pendingResult=null;
const suits={S:'♠',H:'♥',D:'♦',C:'♣'};
async function api(path,data){
 const response=await fetch(path,{method:data?'POST':'GET',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
 let result;try{result=await response.json()}catch{throw Error('Something went wrong. Please reload and try again.');}
 if(!response.ok){const error=Error(result.message||'Please try again.');error.status=response.status;throw error;}return result;
}
function showError(e,dialog=false){const el=$(dialog?'#dialog-error':'#error');el.textContent=e.status===419?'Your session expired. Reload the page to continue.':e.message;el.hidden=false;}
function counters(){for(const g of ['hangman','blackjack','daily']) $('#count-'+g).textContent=access.unlimited?'Unlimited play':access[g]+' free '+(access[g]===1?'play':'plays')+' left';$('#account-label').textContent=access.unlimited?'Your forever pass is active.':'Just you. No sign-up.';$$('[data-unlock]').forEach(b=>{if(access.unlimited)b.textContent='Your unlimited pass ↗';});}
function openDialog(view){$('#purchase-content').hidden=view!=='purchase';$('#paid-content').hidden=view!=='paid';$('#restore-content').hidden=view!=='restore';$('#dialog-error').hidden=true;if(view==='paid')$('#recovery-code').textContent=recovery||'Your code is loading. Please close and reopen this window.';if(!$('#access-dialog').open)$('#access-dialog').showModal();}

function render(){counters();}
$$('[data-unlock]').forEach(b=>b.onclick=()=>openDialog(access.unlimited?'paid':'purchase'));$('.dialog-close').onclick=()=>$('#access-dialog').close();$('#restore-open').onclick=()=>openDialog('restore');
$('#checkout').onclick=async()=>{const b=$('#checkout');b.disabled=true;$('#dialog-error').hidden=true;try{const result=await api('/api/checkout',{});window.location.assign(result.url);}catch(e){showError(e,true);b.disabled=false;}};
$('#restore-form').onsubmit=async e=>{e.preventDefault();const b=$('#restore-form button');b.disabled=true;$('#dialog-error').hidden=true;try{const result=await api('/api/restore',{code:$('#restore-code').value.trim()});access=result.access;recovery=result.recovery_code;render();openDialog('paid');}catch(e){showError(e,true);}finally{b.disabled=false;}};
$('#copy-code').onclick=async()=>{try{await navigator.clipboard.writeText(recovery);$('#copy-code').textContent='Copied — keep it somewhere safe';}catch{$('#copy-code').textContent='Select the code above to copy it';}};

api('/api/status').then(data=>{access=data.access;recovery=data.recovery_code;counters();const payment=new URLSearchParams(location.search).get('payment');if(payment==='success'&&access.unlimited)openDialog('paid');else if(payment==='cancelled')showError(Error('Checkout cancelled. You have not been charged.'));else if(payment==='pending')showError(Error('Payment is being confirmed. Refresh shortly.'));if(payment)history.replaceState({},'',location.pathname);}).catch(showError);
