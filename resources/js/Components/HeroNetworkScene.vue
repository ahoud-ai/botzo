<script setup>
// Ambient WebGL backdrop for the hero — a starfield, a drifting node network,
// and a slowly rotating wireframe icosahedron, layered the way botzo.io's own
// hero background is built (confirmed via their `vendor-three` bundle chunk).
// Recolored to brand green and alpha-tuned per theme rather than copied
// verbatim, since their site is dark-only and ours isn't. Desktop only —
// the parent decorative wrapper is `hidden lg:block`, matching the previous
// 2D canvas network this replaces.
import { ref, onMounted, onUnmounted } from "vue";
import * as THREE from "three";

const container = ref(null);
const canvas = ref(null);

let renderer = null;
let scene = null;
let camera = null;
let animationFrame = null;
let resizeObserver = null;
let themeObserver = null;

let starPoints = null;
let nodePoints = null;
let nodeLines = null;
let wireframeSphere = null;

const STAR_COUNT = 260;
const NODE_COUNT = 42;
const NODE_LINK_DISTANCE = 22;

let nodes = [];
let prefersReducedMotion = false;

let dotSprite = null;

function buildDotSprite() {
  // Points render as hard squares by default in WebGL — a small radial-alpha
  // canvas texture turns them into soft round dots instead, matching the
  // reference's star/node look.
  const size = 64;
  const canvasEl = document.createElement("canvas");
  canvasEl.width = size;
  canvasEl.height = size;
  const ctx = canvasEl.getContext("2d");
  const gradient = ctx.createRadialGradient(size / 2, size / 2, 0, size / 2, size / 2, size / 2);
  gradient.addColorStop(0, "rgba(255,255,255,1)");
  gradient.addColorStop(0.6, "rgba(255,255,255,0.6)");
  gradient.addColorStop(1, "rgba(255,255,255,0)");
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, size, size);
  dotSprite = new THREE.CanvasTexture(canvasEl);
}

function isDarkTheme() {
  return document.documentElement.classList.contains("dark");
}

function themeColors() {
  // Same brand green in both themes — only alpha shifts, matching the rest
  // of this page's pattern language (a colored layer stays texture, not
  // noise, at a lower alpha on a light ground).
  const dark = isDarkTheme();
  return {
    star: dark ? 0.55 : 0.35,
    node: dark ? 0.6 : 0.35,
    link: dark ? 0.28 : 0.18,
    wire: dark ? 0.35 : 0.22,
  };
}

function buildStarfield() {
  const positions = new Float32Array(STAR_COUNT * 3);
  for (let i = 0; i < STAR_COUNT; i++) {
    positions[i * 3] = (Math.random() - 0.5) * 160;
    positions[i * 3 + 1] = (Math.random() - 0.5) * 100;
    positions[i * 3 + 2] = (Math.random() - 0.5) * 80 - 20;
  }
  const geometry = new THREE.BufferGeometry();
  geometry.setAttribute("position", new THREE.BufferAttribute(positions, 3));
  const material = new THREE.PointsMaterial({
    color: 0x25d366,
    size: 1,
    map: dotSprite,
    transparent: true,
    depthWrite: false,
    opacity: themeColors().star,
    sizeAttenuation: true,
  });
  starPoints = new THREE.Points(geometry, material);
  scene.add(starPoints);
}

function buildNodeNetwork() {
  nodes = Array.from({ length: NODE_COUNT }, () => ({
    x: (Math.random() - 0.5) * 110,
    y: (Math.random() - 0.5) * 64,
    z: (Math.random() - 0.5) * 40,
    vx: (Math.random() - 0.5) * 0.05,
    vy: (Math.random() - 0.5) * 0.05,
    vz: (Math.random() - 0.5) * 0.05,
  }));

  const nodeGeometry = new THREE.BufferGeometry();
  const nodePositions = new Float32Array(NODE_COUNT * 3);
  nodeGeometry.setAttribute("position", new THREE.BufferAttribute(nodePositions, 3));
  const nodeMaterial = new THREE.PointsMaterial({
    color: 0x25d366,
    size: 2.6,
    map: dotSprite,
    transparent: true,
    depthWrite: false,
    opacity: themeColors().node,
    sizeAttenuation: true,
  });
  nodePoints = new THREE.Points(nodeGeometry, nodeMaterial);
  scene.add(nodePoints);

  const maxPairs = (NODE_COUNT * (NODE_COUNT - 1)) / 2;
  const lineGeometry = new THREE.BufferGeometry();
  const linePositions = new Float32Array(maxPairs * 2 * 3);
  lineGeometry.setAttribute("position", new THREE.BufferAttribute(linePositions, 3));
  const lineMaterial = new THREE.LineBasicMaterial({
    color: 0x25d366,
    transparent: true,
    opacity: themeColors().link,
  });
  nodeLines = new THREE.LineSegments(lineGeometry, lineMaterial);
  scene.add(nodeLines);
}

function buildWireframeSphere() {
  const geometry = new THREE.IcosahedronGeometry(15, 1);
  const edges = new THREE.EdgesGeometry(geometry);
  const material = new THREE.LineBasicMaterial({
    color: 0x25d366,
    transparent: true,
    opacity: themeColors().wire,
  });
  wireframeSphere = new THREE.LineSegments(edges, material);
  wireframeSphere.position.set(-34, 10, -15);
  scene.add(wireframeSphere);
}

function updateNodeNetwork() {
  if (!prefersReducedMotion) {
    for (const node of nodes) {
      node.x += node.vx;
      node.y += node.vy;
      node.z += node.vz;
      if (node.x <= -55 || node.x >= 55) node.vx *= -1;
      if (node.y <= -32 || node.y >= 32) node.vy *= -1;
      if (node.z <= -20 || node.z >= 20) node.vz *= -1;
    }
  }

  const nodePositions = nodePoints.geometry.attributes.position.array;
  nodes.forEach((node, i) => {
    nodePositions[i * 3] = node.x;
    nodePositions[i * 3 + 1] = node.y;
    nodePositions[i * 3 + 2] = node.z;
  });
  nodePoints.geometry.attributes.position.needsUpdate = true;

  const linePositions = nodeLines.geometry.attributes.position.array;
  let vertexCount = 0;
  for (let i = 0; i < nodes.length; i++) {
    for (let j = i + 1; j < nodes.length; j++) {
      const a = nodes[i];
      const b = nodes[j];
      const dist = Math.hypot(a.x - b.x, a.y - b.y, a.z - b.z);
      if (dist < NODE_LINK_DISTANCE) {
        linePositions[vertexCount++] = a.x;
        linePositions[vertexCount++] = a.y;
        linePositions[vertexCount++] = a.z;
        linePositions[vertexCount++] = b.x;
        linePositions[vertexCount++] = b.y;
        linePositions[vertexCount++] = b.z;
      }
    }
  }
  nodeLines.geometry.attributes.position.needsUpdate = true;
  nodeLines.geometry.setDrawRange(0, vertexCount / 3);
}

function applyThemeColors() {
  const colors = themeColors();
  if (starPoints) starPoints.material.opacity = colors.star;
  if (nodePoints) nodePoints.material.opacity = colors.node;
  if (nodeLines) nodeLines.material.opacity = colors.link;
  if (wireframeSphere) wireframeSphere.material.opacity = colors.wire;
}

function resizeToContainer() {
  const el = container.value;
  if (!el || !renderer || !camera) return;
  const { width, height } = el.getBoundingClientRect();
  if (width === 0 || height === 0) return;
  renderer.setSize(width, height, false);
  camera.aspect = width / height;
  camera.updateProjectionMatrix();
}

function animate() {
  if (wireframeSphere && !prefersReducedMotion) {
    wireframeSphere.rotation.y += 0.0022;
    wireframeSphere.rotation.x += 0.0009;
  }
  updateNodeNetwork();
  renderer.render(scene, camera);
  animationFrame = requestAnimationFrame(animate);
}

function init() {
  if (!window.matchMedia("(min-width: 1024px)").matches) return;
  if (!canvas.value || !container.value) return;

  prefersReducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

  renderer = new THREE.WebGLRenderer({ canvas: canvas.value, alpha: true, antialias: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.setClearColor(0x000000, 0);

  scene = new THREE.Scene();
  camera = new THREE.PerspectiveCamera(50, 1, 0.1, 300);
  camera.position.z = 62;

  buildDotSprite();
  buildStarfield();
  buildNodeNetwork();
  buildWireframeSphere();
  resizeToContainer();

  resizeObserver = new ResizeObserver(resizeToContainer);
  resizeObserver.observe(container.value);

  themeObserver = new MutationObserver(applyThemeColors);
  themeObserver.observe(document.documentElement, { attributes: true, attributeFilter: ["class"] });

  animate();
}

function dispose() {
  if (animationFrame) cancelAnimationFrame(animationFrame);
  resizeObserver?.disconnect();
  themeObserver?.disconnect();

  [starPoints, nodePoints, nodeLines, wireframeSphere].forEach((obj) => {
    if (!obj) return;
    obj.geometry?.dispose();
    obj.material?.dispose();
  });
  dotSprite?.dispose();
  renderer?.dispose();
}

onMounted(init);
onUnmounted(dispose);
</script>

<template>
  <div ref="container" class="absolute inset-0 h-full w-full">
    <canvas ref="canvas" class="h-full w-full"></canvas>
  </div>
</template>
