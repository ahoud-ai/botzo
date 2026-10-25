// Only the three.js parts HeroNetworkScene uses, so the lazily loaded chunk stays tree-shaken
// (a bare `import("three")` pulls in the whole library).
export {
    BufferAttribute,
    BufferGeometry,
    CanvasTexture,
    EdgesGeometry,
    IcosahedronGeometry,
    LineBasicMaterial,
    LineSegments,
    PerspectiveCamera,
    Points,
    PointsMaterial,
    Scene,
    WebGLRenderer,
} from 'three';
