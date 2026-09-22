window.drawDots=(root,s,names,enabled,onMove)=>{
 root.replaceChildren();const size=s?.size||3;root.style.gridTemplateColumns=root.style.gridTemplateRows=Array.from({length:size*2+1},(_,i)=>i%2?'minmax(0,1fr)':'16px').join(' ');root.style.width=Math.max(320,size*72)+'px';root.style.minWidth=size>=5?size*60+'px':'0';
 for(let row=0;row<size*2+1;row++)for(let col=0;col<size*2+1;col++){
  const el=document.createElement(row%2!==col%2?'button':'span');el.style.gridRow=row+1;el.style.gridColumn=col+1;
  if(row%2===0&&col%2===0)el.className='dot-point';
  else if(row%2&&col%2){const b=(row-1)/2*size+(col-1)/2,owner=s?.boxes?.[b];el.className='dot-box';el.dataset.owner=owner||'';el.textContent=owner?(size>=5?(names[owner]||owner).split(/[- ]/).slice(0,2).map(w=>w[0]).join('').toUpperCase():(names[owner]||owner).split('-').slice(0,2).join(' ')):'';el.title=owner?names[owner]:'';el.setAttribute('aria-label','Box '+(b+1)+(owner?' claimed by '+names[owner]:', unclaimed'));}
  else{const horizontal=row%2===0,edge=horizontal?row/2*size+(col-1)/2:size*(size+1)+(row-1)/2*(size+1)+col/2;el.type='button';el.className='dot-edge '+(horizontal?'horizontal':'vertical');el.dataset.owner=s?.edges?.[edge]||'';el.disabled=!enabled||!!s?.edges?.[edge];el.setAttribute('aria-label',(horizontal?'Horizontal':'Vertical')+' line, row '+(Math.floor(row/2)+1)+', column '+(Math.floor(col/2)+1)+(s?.edges?.[edge]?', claimed by '+names[s.edges[edge]]:''));el.onclick=()=>onMove(edge);}
  root.append(el);
 }
};
