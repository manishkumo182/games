const $=s=>document.querySelector(s), $$=s=>[...document.querySelectorAll(s)];
let current=document.body.dataset.game||'hangman', rounds={}, access={hangman:5,blackjack:5,daily:5,unlimited:false}, busy=false, ready=false, recovery=null, pendingResult=null, dailyCalendar=null, resetTimer=null;
const suits={S:'♠',H:'♥',D:'♦',C:'♣'};
async function api(path,data){
 const response=await fetch(path,{method:data?'POST':'GET',headers:{'Accept':'application/json','Content-Type':'application/json','X-CSRF-TOKEN':$('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
 let result;try{result=await response.json()}catch{throw Error('Something went wrong. Please reload and try again.');}
 if(!response.ok){const error=Error(result.message||'Please try again.');error.status=response.status;throw error;}return result;
}
function showError(e,dialog=false){const el=$(dialog?'#dialog-error':'#error');el.textContent=e.status===419?'Your session expired. Reload the page to continue.':e.message;el.hidden=false;}
function counters(){for(const g of ['hangman','blackjack','daily']) if($('#count-'+g)) $('#count-'+g).textContent=access.unlimited?'Unlimited play':access[g]+' free '+(access[g]===1?'play':'plays')+' left';$('#account-label').textContent=access.unlimited?'Your forever pass is active.':'Just you. No sign-up.';$$('[data-unlock]').forEach(b=>{if(access.unlimited)b.textContent='Your unlimited pass ↗';});}
function select(game){current=game;$$('.game-pick[data-game]').forEach(b=>{const active=b.dataset.game===game;b.classList.toggle('selected',active);b.setAttribute('aria-pressed',active);});$('#hangman-view').hidden=game!=='hangman';$('#hangman-settings').hidden=game!=='hangman';$('#blackjack-view').hidden=game!=='blackjack';$('#blackjack-settings').hidden=game!=='blackjack';$('#daily-view').hidden=game!=='daily';$('#daily-settings').hidden=game!=='daily';$('#game-title').textContent={hangman:'Hangman',blackjack:'Blackjack',daily:'Daily Word'}[game];$('#game-category').textContent={hangman:'THE WORD CLASSIC',blackjack:'THE TABLE CLASSIC',daily:'ONE WORD. EVERY DAY.'}[game];$('#error').hidden=true;render();}
for(const row of ['QWERTYUIOP','ASDFGHJKL','ZXCVBNM']){const wrap=document.createElement('div');wrap.className='key-row';for(const l of row){const b=document.createElement('button');b.className='key';b.textContent=l;b.dataset.letter=l;b.setAttribute('aria-label','Guess '+l);b.addEventListener('click',()=>move('guess',l));wrap.append(b);}$('#keyboard').append(wrap);}
function render(){
 counters();const s=rounds[current]?.state;$('#round-badge').textContent=!ready?'Connecting…':s?.status==='playing'?'Round in progress':s?'Round complete':'Ready when you are';
 if(current==='hangman'){
 const playing=s?.status==='playing';if(playing)$('input[name="difficulty"][value="'+s.difficulty+'"]').checked=true;$('#difficulty').disabled=busy||playing||!ready;
 if(s){$('#word').replaceChildren(...s.letters.map(l=>{const e=document.createElement('span');e.textContent=l||' ';return e;}));$('#word').setAttribute('aria-label',s.letters.map(l=>l||'blank').join(' '));$('#word-caption').textContent=s.letters.length+' letters · '+s.difficulty;$('#mistakes').textContent=s.remaining+' incorrect guesses left'+(s.wrong.length?' · Missed: '+s.wrong.join(', '):'');}
 const mistakes=s?.wrong.length||0;const partCount=s?Math.ceil(mistakes*8/s.limit):0;$$('[data-part]').forEach(e=>e.classList.toggle('drawn',+e.dataset.part<=partCount));
 $$('.key').forEach(b=>{const guessed=s?.guesses.includes(b.dataset.letter);b.disabled=!ready||busy||!playing||guessed;b.classList.toggle('wrong',!!guessed&&s.wrong.includes(b.dataset.letter));b.classList.toggle('correct',!!guessed&&!s.wrong.includes(b.dataset.letter));});
 $('#hangman-result').textContent=s?.status==='won'?'Nicely done. You found the word!':s?.status==='lost'?'That one got away. The word was '+s.letters.join('')+'.':'';
 $('#hangman-start').disabled=!ready||busy||playing;$('#hangman-start').textContent=playing?'Round in progress':!access.unlimited&&access.hangman===0?'Unlock to keep playing':s?'Play another word ↗':"Let’s play ↗";
 $('#hangman-help').textContent=playing?'Tap a letter or use your keyboard.':'Your keyboard works here, too.';
 }else if(current==='daily'){renderDaily(s);
 }else{
 if(s){$('#dealer-cards').replaceChildren(...s.dealer.map(card));$('#dealer-total').textContent=s.dealer_total+(s.status==='playing'?' + ?':'');$('#player-hands').replaceChildren(...s.hands.map((h,i)=>{const wrap=document.createElement('div');wrap.className='player-hand'+(s.status==='playing'&&s.active===i?' active':'');const hand=document.createElement('div');hand.className='hand';hand.append(...h.cards.map(card));const label=document.createElement('div');label.className='table-label';label.textContent=(s.hands.length>1?'HAND '+(i+1):'YOU')+' · '+h.total+' · '+h.bet+' chips'+(h.result?' · '+h.result:'');wrap.append(hand,label);return wrap;}));}
 $('#blackjack-result').textContent=!s?'':s.status==='playing'?(s.hands.length>1?'Playing hand '+(s.active+1)+'. Choose your move.':'Your move. Hit, stand, or double down.'):roundMessage(s).title+' · '+(s.net>0?'+':'')+s.net+' chips.';
 $('#blackjack-start').disabled=!ready||busy||s?.status==='playing';$('#blackjack-start').textContent=!access.unlimited&&access.blackjack===0?'Unlock to play':s?'Deal again ↗':'Deal me in ↗';
 $$('[data-action]').forEach(b=>b.disabled=!ready||busy||!s?.actions?.includes(b.dataset.action));
 $('#review-result').hidden=s?.status!=='finished';
 if(!busy&&pendingResult===rounds.blackjack?.id&&s?.status==='finished'&&!$('#access-dialog').open){pendingResult=null;showRoundResult();}
 }
}
function roundMessage(s){
 const natural=s.hands.length===1&&s.hands[0].result==='blackjack';
 const allBust=s.hands.every(h=>h.result==='bust');
 const allPush=s.hands.every(h=>h.result==='push');
 const dealerNatural=s.dealer.length===2&&s.dealer_total===21;
 return {title:natural?'Blackjack!':allBust?'You went bust':s.net>0?'You win!':s.net<0?'Dealer wins':allPush?'It’s a push':'An even round',
 description:natural?'An ace and a ten-value card. Your blackjack pays 3:2.':allBust?'Your '+(s.hands.length>1?'hands went':'hand went')+' over 21.':allPush?'You and the dealer tied. Your stake comes back to you.':s.hands.length>1?'Here’s how each split hand finished.':dealerNatural?'The dealer has a natural blackjack.':s.dealer_total>21?'The dealer went over 21. Your hand wins.':s.net>0?'Your '+s.hands[0].total+' beats the dealer’s '+s.dealer_total+'.':'The dealer’s '+s.dealer_total+' beats your '+s.hands[0].total+'.'};
}
function showRoundResult(){
 const s=rounds.blackjack?.state;if(!s||s.status!=='finished')return;
 const message=roundMessage(s), dialog=$('#result-dialog');
 dialog.dataset.outcome=s.net>0?'win':s.net<0?'loss':'push';
 $('#result-emblem').textContent=s.hands[0].result==='blackjack'?'♠':s.net>0?'✦':s.net<0?'♣':'=';
 $('#result-title').textContent=message.title;
 $('#result-amount').textContent=(s.net>0?'+':'')+s.net+' chips';
 $('#result-description').textContent=message.description;
 const labels={blackjack:'Blackjack · 3:2',win:'Won',lose:'Lost',bust:'Bust',push:'Push'};
 $('#result-breakdown').replaceChildren(...s.hands.map((h,i)=>{
  const row=document.createElement('div');row.className='result-hand';
  const label=document.createElement('span');label.textContent=(s.hands.length>1?'Hand '+(i+1):'Your hand')+' · '+h.total;
  const outcome=document.createElement('strong');outcome.textContent=labels[h.result]+' · '+(h.net>0?'+':'')+h.net;
  row.append(label,outcome);return row;
 }));
 const dealer=document.createElement('div');dealer.className='result-hand dealer-summary';dealer.textContent='Dealer · '+s.dealer_total+(s.dealer_total>21?' · Bust':'');$('#result-breakdown').append(dealer);
 $('#result-next').textContent=!access.unlimited&&access.blackjack===0?'Unlock unlimited · $1':'Deal again ↗';
 if(!dialog.open)dialog.showModal();
}
$('#review-result').onclick=showRoundResult;
$('#result-close').onclick=$('#result-review').onclick=()=>$('#result-dialog').close();
$('#result-next').onclick=()=>{$('#result-dialog').close();select('blackjack');start();};
function card(c){const e=document.createElement('div');e.className='playing-card';if(!c){e.classList.add('back');e.setAttribute('aria-label','Face-down card');return e;}if(['H','D'].includes(c.suit))e.classList.add('red');e.setAttribute('aria-label',c.rank+' of '+({S:'spades',H:'hearts',D:'diamonds',C:'clubs'}[c.suit]));const rank=document.createElement('span');rank.textContent=c.rank;const small=document.createElement('span');small.className='suit';small.textContent=suits[c.suit];const large=document.createElement('span');large.className='big-suit';large.textContent=suits[c.suit];e.append(rank,small,large);return e;}
async function start(){if(busy||!ready)return;if(!access.unlimited&&access[current]===0){openDialog('purchase');return;}busy=true;render();$('#error').hidden=true;try{const game=current;const result=await api('/api/games/'+game,{difficulty:$('input[name="difficulty"]:checked').value});rounds[game]=result;access=result.access;if(game==='blackjack'&&result.state.status==='finished')pendingResult=result.id;}catch(e){if(e.status===402)openDialog('purchase');else showError(e);}finally{busy=false;render();}}
async function move(action,letter){if(busy||!ready)return;const round=rounds[current];if(!round||round.state.status!=='playing')return;busy=true;render();$('#error').hidden=true;try{const result=await api('/api/rounds/'+round.id,{action,...(letter?{letter}: {})});rounds[result.game]=result;access=result.access;if(result.game==='blackjack'&&result.state.status==='finished')pendingResult=result.id;}catch(e){showError(e);try{await refresh();}catch{}}finally{busy=false;render();}}
function openDialog(view){$('#purchase-content').hidden=view!=='purchase';$('#paid-content').hidden=view!=='paid';$('#restore-content').hidden=view!=='restore';$('#dialog-error').hidden=true;if(view==='paid')$('#recovery-code').textContent=recovery||'Your code is loading. Please close and reopen this window.';if(!$('#access-dialog').open)$('#access-dialog').showModal();}
$$('.game-pick[data-game]').forEach(b=>b.onclick=()=>select(b.dataset.game));$$('[data-unlock]').forEach(b=>b.onclick=()=>openDialog(access.unlimited?'paid':'purchase'));$('#hangman-start').onclick=start;$('#blackjack-start').onclick=start;$$('[data-action]').forEach(b=>b.onclick=()=>move(b.dataset.action));$('.dialog-close').onclick=()=>$('#access-dialog').close();$('#restore-open').onclick=()=>openDialog('restore');
document.addEventListener('keydown',e=>{if(e.ctrlKey||e.metaKey||e.altKey||e.repeat||($('#access-dialog').open||$('#result-dialog').open)||['INPUT','TEXTAREA','SELECT'].includes(document.activeElement.tagName))return;if(current==='daily'&&/^[a-z]$/i.test(e.key)&&!$('#daily-input').disabled){e.preventDefault();$('#daily-input').value=($('#daily-input').value+e.key.toUpperCase()).slice(0,5);return;}if(current==='hangman'&&/^[a-z]$/i.test(e.key)){const b=$('[data-letter="'+e.key.toUpperCase()+'"]');if(b&&!b.disabled){e.preventDefault();move('guess',e.key.toUpperCase());}}});
$('#checkout').onclick=async()=>{const b=$('#checkout');b.disabled=true;$('#dialog-error').hidden=true;try{const result=await api('/api/checkout',{});window.location.assign(result.url);}catch(e){showError(e,true);b.disabled=false;}};
$('#restore-form').onsubmit=async e=>{e.preventDefault();const b=$('#restore-form button');b.disabled=true;$('#dialog-error').hidden=true;try{const result=await api('/api/restore',{code:$('#restore-code').value.trim()});access=result.access;recovery=result.recovery_code;render();openDialog('paid');}catch(e){showError(e,true);}finally{b.disabled=false;}};
$('#copy-code').onclick=async()=>{try{await navigator.clipboard.writeText(recovery);$('#copy-code').textContent='Copied — keep it somewhere safe';}catch{$('#copy-code').textContent='Select the code above to copy it';}};
async function refresh(){const data=await api('/api/status');dailyCalendar=data.daily;scheduleReset();access=data.access;rounds=data.rounds;recovery=data.recovery_code;ready=true;render();}
function scheduleReset(){
 clearTimeout(resetTimer);if(!dailyCalendar)return;
 resetTimer=setTimeout(()=>refresh().catch(showError),Math.max(1000,Date.parse(dailyCalendar.resets_at)-Date.now()+1000));
}
document.addEventListener('visibilitychange',()=>{if(!document.hidden&&dailyCalendar&&Date.now()>=Date.parse(dailyCalendar.resets_at))refresh().catch(showError);});
function renderDaily(s){
 $('#daily-date').textContent=dailyCalendar?'Puzzle · '+dailyCalendar.date:'Today’s puzzle';
 $('#daily-reset').textContent='New word at 00:00 UTC';
 const playing=s?.status==='playing';
 $('#daily-board').replaceChildren(...Array.from({length:6},(_,i)=>{
  const row=document.createElement('div');row.className='daily-row';const guess=s?.guesses[i];row.setAttribute('aria-label',guess?guess.word+': '+guess.colors.join(', '):'Guess '+(i+1));
  for(let j=0;j<5;j++){const tile=document.createElement('span');tile.className='tile '+(guess?guess.colors[j]:'');tile.textContent=guess?guess.word[j]:'';row.append(tile);}return row;
 }));
 $('#daily-input').disabled=$('#daily-submit').disabled=!ready||busy||!playing;
 $('#daily-form').hidden=!!s&&!playing;
 $('#daily-start').hidden=!!s;
 $('#daily-start').disabled=!ready||busy;
 $('#daily-start').textContent=!access.unlimited&&access.daily===0?'Unlock today’s word · $1':'Play today’s word ↗';
 $('#daily-feedback').textContent=!s?'One word. Six guesses. A fresh start every day.':playing?(6-s.guesses.length)+' guesses left.':s.status==='won'?'You found it in '+s.guesses.length+'/6! Come back tomorrow.':'Today’s word was '+s.answer+'. A fresh puzzle arrives tomorrow.';
 const colors={};const priority={absent:1,present:2,correct:3};for(const guess of s?.guesses||[])for(let i=0;i<5;i++)if((priority[guess.colors[i]]||0)>(priority[colors[guess.word[i]]]||0))colors[guess.word[i]]=guess.colors[i];
 $$('#daily-keyboard button').forEach(b=>{b.disabled=!ready||busy||!playing;if(b.dataset.dailyLetter)b.className='key '+(colors[b.dataset.dailyLetter]||'');});
}
for(const row of ['QWERTYUIOP','ASDFGHJKL','ZXCVBNM']){const wrap=document.createElement('div');wrap.className='key-row';for(const l of row){const b=document.createElement('button');b.type='button';b.className='key';b.textContent=l;b.dataset.dailyLetter=l;b.setAttribute('aria-label','Type '+l);b.onclick=()=>{$('#daily-input').value=($('#daily-input').value+l).slice(0,5);$('#daily-input').focus();};wrap.append(b);}$('#daily-keyboard').append(wrap);}
const erase=document.createElement('button');erase.type='button';erase.className='text-button';erase.textContent='⌫ Delete letter';erase.onclick=()=>{$('#daily-input').value=$('#daily-input').value.slice(0,-1);$('#daily-input').focus();};$('#daily-keyboard').append(erase);
$('#daily-start').onclick=start;
$('#daily-input').addEventListener('input',e=>{e.target.value=e.target.value.replace(/[^a-z]/gi,'').toUpperCase().slice(0,5);});
$('#daily-form').onsubmit=async e=>{
 e.preventDefault();if(busy||!ready)return;const word=$('#daily-input').value.toUpperCase();if(!/^[A-Z]{5}$/.test(word)){showError(Error('Enter a five-letter word.'));return;}
 busy=true;render();$('#error').hidden=true;
 try{const result=await api('/api/rounds/'+rounds.daily.id,{action:'word',word});rounds.daily=result;access=result.access;$('#daily-input').value='';}
 catch(error){showError(error);if(error.status===409)await refresh().catch(showError);}
 finally{busy=false;render();if(!$('#daily-input').disabled)$('#daily-input').focus();}
};
select(current);refresh().then(()=>{const payment=new URLSearchParams(location.search).get('payment');if(payment==='success'&&access.unlimited)openDialog('paid');else if(payment==='cancelled'){showError(Error('Checkout cancelled. You have not been charged.'));}else if(payment==='pending'){showError(Error('Your payment is still being confirmed. Refresh shortly to check your pass.'));}if(payment)history.replaceState({},'',location.pathname);}).catch(showError);
