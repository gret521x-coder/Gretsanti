#!/usr/bin/env node
/* ==========================================================================
   Cut the tower art out of the reference sheet.

     node labs/assets/fortress/slice-towers.js

   Read towers/_sheet.jpg (4 columns x 5 rows of Kingdom Rush towers), take the
   first three rows — those are levels 1, 2 and 3 — and write 12 PNGs with the
   grass, dirt and stray scenery knocked out, trimmed and scaled to 150 px tall.
   Row order left to right: archery, mage, catapult, barracks.
   Then run build-sprites.js to bake them into the game.
   ========================================================================== */
'use strict';
const sharp=require('sharp'), fs=require('fs');
const SRC=require('path').join(__dirname,'towers','_sheet.jpg');
const COLS=[[3,118],[121,236],[239,354],[357,472]];
const ROWS=[[3,114],[119,228],[234,344],[349,459],[465,573]];
const NAMES=['archery','mage','catapult','barracks'];   // left to right on the sheet
const TARGET_H=150, DEBUG=process.env.DBG==='1';

// ground classes we want gone
const ground=(r,g,b)=>
  (g>r+12 && g>b+18) ||                       // grass and olive scrub
  (r>150 && g>115 && r>b+45 && g>b+30) ||      // tan dirt / path
  (r>212 && g>212 && b>205) ||                 // white margin
  (g>r+6 && g>b+6 && r+g+b<270);               // dark bushes

(async()=>{
  const {data,info}=await sharp(SRC).ensureAlpha().raw().toBuffer({resolveWithObject:true});
  const W=info.width,ch=info.channels;
  const tiles=[];
  for(let row=0;row<3;row++) for(let col=0;col<4;col++){
    const [x0,x1]=COLS[col], [y0,y1]=ROWS[row];
    const w=x1-x0+1, h=y1-y0+1;
    const clear=new Uint8Array(w*h), q=[];
    const seed=(x,y)=>{ const k=y*w+x; if(clear[k]) return; clear[k]=1; q.push(k); };
    for(let x=0;x<w;x++){ seed(x,0); seed(x,h-1); }
    for(let y=0;y<h;y++){ seed(0,y); seed(w-1,y); }
    for(let qi=0;qi<q.length;qi++){
      const k=q[qi], x=k%w, y=(k-x)/w;
      for(const [nx,ny] of [[x+1,y],[x-1,y],[x,y+1],[x,y-1]]){
        if(nx<0||ny<0||nx>=w||ny>=h) continue;
        const nk=ny*w+nx; if(clear[nk]) continue;
        const i=((y0+ny)*W+(x0+nx))*ch;
        if(ground(data[i],data[i+1],data[i+2])){ clear[nk]=1; q.push(nk); }
      }
    }
    const comp=new Int32Array(w*h).fill(-1), comps=[];
    for(let sy=0;sy<h;sy++)for(let sx=0;sx<w;sx++){
      const sk=sy*w+sx; if(clear[sk]||comp[sk]>=0) continue;
      const id=comps.length; comp[sk]=id; const cells=[[sx,sy]], stack=[[sx,sy]];
      while(stack.length){
        const [x,y]=stack.pop();
        for(const [nx,ny] of [[x+1,y],[x-1,y],[x,y+1],[x,y-1]]){
          if(nx<0||ny<0||nx>=w||ny>=h) continue;
          const nk=ny*w+nx; if(clear[nk]||comp[nk]>=0) continue;
          comp[nk]=id; stack.push([nx,ny]); cells.push([nx,ny]);
        }
      }
      comps.push(cells);
    }
    const info=comps.map(cells=>{
      let sx=0, sy=0, minX=1e9, maxX=-1, minY=1e9, maxY=-1;
      for(const [x,y] of cells){ sx+=x; sy+=y; if(x<minX)minX=x; if(x>maxX)maxX=x; if(y<minY)minY=y; if(y>maxY)maxY=y; }
      return {cells, area:cells.length, cx:sx/cells.length, cy:sy/cells.length, minX, maxX, minY, maxY};
    });
    const main=info.slice().sort((a,b)=>b.area-a.area)[0];
    const keep=new Uint8Array(w*h);
    for(const o of info){
      if(o===main){ for(const [x,y] of o.cells) keep[y*w+x]=1; continue; }
      if(o.area<45||o.area>420) continue;
      if(o.cy<0.45) continue;
      if(Math.abs(o.cx-main.cx)>0.30*w) continue;
      for(const [x,y] of o.cells) keep[y*w+x]=1;
    }
    let minX=w,minY=h,maxX=-1,maxY=-1, kept=0;
    const cut=Buffer.alloc(w*h*4);
    for(let y=0;y<h;y++)for(let x=0;x<w;x++){
      const k=y*w+x, o=k*4, on=keep[k], i=((y0+y)*W+(x0+x))*ch;
      cut[o]=data[i];cut[o+1]=data[i+1];cut[o+2]=data[i+2];cut[o+3]=on?255:0;
      if(on){kept++;minX=Math.min(minX,x);minY=Math.min(minY,y);maxX=Math.max(maxX,x);maxY=Math.max(maxY,y);}
    }
    let cw=maxX-minX+1; const chh=maxY-minY+1;
    if(cw > chh*1.25){                       // trim far-out scenery so towers stay tower-shaped
      const half=Math.round(chh*1.25/2);
      let c=Math.round(main.cx);
      c=Math.max(minX+half, Math.min(maxX-half, c));
      minX=Math.max(minX, c-half); maxX=minX+half*2; cw=maxX-minX+1;
    }
    const cropped=Buffer.alloc(cw*chh*4);
    for(let y=0;y<chh;y++)for(let x=0;x<cw;x++){
      const si=((y+minY)*w+(x+minX))*4, di=(y*cw+x)*4;
      cut.copy(cropped,di,si,si+4);
    }
    const scale=TARGET_H/chh;
    const name=NAMES[col]+'-'+(row+1);
    const png=await sharp(cropped,{raw:{width:cw,height:chh,channels:4}})
      .resize(Math.round(cw*scale),TARGET_H,{kernel:'nearest'}).png({compressionLevel:9,palette:true,colours:96}).toBuffer();
    fs.writeFileSync(require('path').join(__dirname,'towers',name+'.png'),png);
    tiles.push({name,png});
    console.log(name, cw+'x'+chh, 'kept', Math.round(100*kept/(w*h))+'%');
  }
  const CW=190,CH=190,PAD=6, gridW=4*(CW+PAD)+PAD, gridH=3*(CH+PAD)+PAD, layers=[];
  for(let i=0;i<tiles.length;i++){
    const col=i%4,row=Math.floor(i/4);
    const meta=await sharp(tiles[i].png).metadata();
    layers.push({input:tiles[i].png,left:PAD+col*(CW+PAD)+Math.round((CW-meta.width)/2),top:PAD+row*(CH+PAD)+(CH-meta.height)});
  }
  await sharp({create:{width:gridW,height:gridH,channels:4,background:{r:74,g:107,b:50,alpha:1}}})
    .composite(layers).png().toFile('/tmp/fortress-towers-preview.png');
  console.log('preview written to /tmp/fortress-towers-preview.png');
})();
